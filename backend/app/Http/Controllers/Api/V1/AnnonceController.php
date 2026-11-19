<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnonceResource;
use App\Models\Annonce;
use App\Models\Commune;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AnnonceController extends Controller
{
    private const RELATIONS = [
        'vendeur:id,first_name,last_name', 'lieuRetrait.zone', 'lieuRetrait.commune',
        'offresRepas.lieuRetrait.zone', 'offresRepas.lieuRetrait.commune',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['sometimes', 'required', 'string', 'in:OBJET,REPAS'],
            'q' => ['nullable', 'string', 'max:100'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $query = Annonce::query()->with(self::RELATIONS);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['q']) && trim($filters['q']) !== '') {
            $text = '%'.trim($filters['q']).'%';
            $query->where(function (Builder $query) use ($text): void {
                $query->where('titre', 'like', $text)
                    ->orWhere('description', 'like', $text)
                    ->orWhere('categorie', 'like', $text);
            });
        }

        if (! empty($filters['zone_id'])) {
            $zoneId = (int) $filters['zone_id'];
            $query->where(function (Builder $query) use ($zoneId): void {
                $query->whereHas('lieuRetrait', fn (Builder $place) => $place->where('zone_id', $zoneId))
                    ->orWhereHas('offresRepas.lieuRetrait', fn (Builder $place) => $place->where('zone_id', $zoneId));
            });
        }

        $annonces = $query
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return AnnonceResource::collection($annonces);
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return AnnonceResource::collection(
            Annonce::query()->with(self::RELATIONS)
                ->where('vendeur_id', $request->user()->getAuthIdentifier())
                ->orderByDesc('id')
                ->paginate(12)
        );
    }

    public function show(Annonce $annonce): AnnonceResource
    {
        $annonce->load(self::RELATIONS);

        return new AnnonceResource($annonce);
    }

    public function destroy(Request $request, int $annonce): Response
    {
        DB::transaction(function () use ($request, $annonce): void {
            $ownedAnnouncement = Annonce::query()
                ->where('vendeur_id', $request->user()->getAuthIdentifier())
                ->lockForUpdate()
                ->findOrFail($annonce);

            $this->ensureCanChange($ownedAnnouncement);

            if ($ownedAnnouncement->type === 'REPAS') {
                DB::table('offres_repas')->where('annonce_id', $ownedAnnouncement->id)->delete();
                DB::table('repas')->where('annonce_id', $ownedAnnouncement->id)->delete();
            }

            $ownedAnnouncement->delete();
        });

        return response()->noContent();
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function edit(Request $request, int $annonce): JsonResponse
    {
        $owned = $this->ownedAnnouncement($request, $annonce);
        $this->ensureCanChange($owned);
        $offer = $owned->type === 'REPAS' ? $owned->offresRepas()->first() : null;
        $place = ($offer ?? $owned)->lieuRetrait;

        return response()->json(['data' => [
            ...$owned->only(['id', 'type', 'titre', 'description', 'categorie', 'prix']),
            'image_url' => asset($owned->image),
            'type_lieu' => $place?->type,
            'adresse' => $place?->adresse,
            'commune_id' => $place?->commune_id,
            'commune' => $place?->commune?->nom,
            'code_postal' => $place?->commune?->code_postal,
            'debut_retrait' => ($offer ?? $owned)->debut_retrait?->setTimezone('Europe/Brussels')->format('Y-m-d\TH:i'),
            'fin_retrait' => ($offer ?? $owned)->fin_retrait?->setTimezone('Europe/Brussels')->format('Y-m-d\TH:i'),
            'limite_achat' => $offer?->limite_achat?->setTimezone('Europe/Brussels')->format('Y-m-d\TH:i'),
            'portions_restantes' => $offer?->portions_restantes,
        ]])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, int $annonce): JsonResponse
    {
        $owned = $this->ownedAnnouncement($request, $annonce);
        $this->ensureCanChange($owned);
        $request->merge(['type' => $owned->type]);

        return $this->save($request, $owned);
    }

    private function ownedAnnouncement(Request $request, int $annonce): Annonce
    {
        return Annonce::query()->where('vendeur_id', $request->user()->getAuthIdentifier())->findOrFail($annonce);
    }

    private function ensureCanChange(Annonce $annonce): void
    {
        if ($annonce->etat_objet === 'VENDU' || DB::table('commandes')
            ->where('annonce_id', $annonce->id)
            ->whereIn('statut', ['EN_COURS', 'CONFIRMEE'])
            ->exists()) {
            abort(409, 'Cette annonce a déjà une commande en cours ou confirmée.');
        }
    }

    private function save(Request $request, ?Annonce $existing = null): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:OBJET,REPAS'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'categorie' => ['required', 'string', 'max:100'],
            'prix' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,99999999.99'],
            'image' => [$existing === null ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'type_lieu' => ['required', 'in:DOMICILE,POINT'],
            'adresse' => ['required', 'string', 'max:255'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
            'commune' => ['required_without:commune_id', 'nullable', 'string', 'max:120'],
            'code_postal' => ['required_without:commune_id', 'nullable', 'digits:4'],
            'debut_retrait' => ['required', 'date_format:Y-m-d\TH:i'],
            'fin_retrait' => ['required', 'date_format:Y-m-d\TH:i'],
            'limite_achat' => ['exclude_unless:type,REPAS', 'required', 'date_format:Y-m-d\TH:i'],
            'portions_restantes' => ['exclude_unless:type,REPAS', 'required', 'integer', 'between:1,2147483647'],
        ]);

        if (isset($data['commune_id'])) {
            $commune = Commune::find($data['commune_id']);
        } else {
            $matches = Commune::query()->where('nom', $data['commune'])
                ->where('code_postal', $data['code_postal'])->limit(2)->get();
            $commune = $matches->count() === 1 ? $matches->first() : null;
        }

        if ($commune === null) {
            throw ValidationException::withMessages([
                'commune' => 'Choisis une commune et un code postal reconnus dans Home Made.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m-d\TH:i', $data['debut_retrait'], 'Europe/Brussels')->utc();
        $end = Carbon::createFromFormat('Y-m-d\TH:i', $data['fin_retrait'], 'Europe/Brussels')->utc();

        if (! $start->isFuture() || $start->gte($end)) {
            throw ValidationException::withMessages([
                'debut_retrait' => 'Choisis un retrait à venir dont le début précède la fin.',
            ]);
        }

        $deadline = $data['type'] === 'REPAS'
            ? Carbon::createFromFormat('Y-m-d\TH:i', $data['limite_achat'], 'Europe/Brussels')->utc()
            : null;

        if ($deadline !== null && (! $deadline->isFuture() || $deadline->gte($start))) {
            throw ValidationException::withMessages([
                'limite_achat' => 'Choisis une limite d’achat à venir, avant le début du retrait.',
            ]);
        }

        $seller = $request->user()->getAuthIdentifier();
        $disk = Storage::disk('public');
        $path = $request->hasFile('image') ? $disk->putFile('produits', $request->file('image')) : null;

        if ($path === false) {
            abort(500, 'Impossible d’enregistrer la photo.');
        }

        try {
            $id = DB::transaction(function () use ($data, $commune, $seller, $start, $end, $deadline, $path, $existing): int {
                $current = $existing === null ? null : Annonce::query()->lockForUpdate()->findOrFail($existing->id);
                if ($current !== null) {
                    $this->ensureCanChange($current);
                }

                $place = [
                    'vendeur_id' => $seller,
                    'zone_id' => $commune->zone_id,
                    'commune_id' => $commune->id,
                    'type' => $data['type_lieu'],
                    'adresse' => $data['adresse'],
                ];
                $placeId = ($current === null ? null : DB::table('lieux_retrait')->where($place)->value('id'))
                    ?? DB::table('lieux_retrait')->insertGetId($place);

                $isObject = $data['type'] === 'OBJET';
                $announcement = [
                    'vendeur_id' => $seller,
                    'type' => $data['type'],
                    'titre' => $data['titre'],
                    'description' => $data['description'],
                    'categorie' => $data['categorie'],
                    'prix' => $data['prix'],
                    'image' => $path === null ? $current->image : '/storage/'.$path,
                    'etat_objet' => $isObject ? 'DISPONIBLE' : null,
                    'lieu_retrait_id' => $isObject ? $placeId : null,
                    'debut_retrait' => $isObject ? $start->toDateTimeString() : null,
                    'fin_retrait' => $isObject ? $end->toDateTimeString() : null,
                ];
                if ($current === null) {
                    $announcementId = DB::table('annonces')->insertGetId($announcement);
                } else {
                    $announcementId = $current->id;
                    DB::table('annonces')->where('id', $announcementId)->update($announcement);
                }

                if ($deadline !== null) {
                    $meal = [
                        'annonce_id' => $announcementId,
                        'vendeur_id' => $seller,
                    ];
                    $mealId = DB::table('repas')->where($meal)->value('id')
                        ?? DB::table('repas')->insertGetId($meal);

                    DB::table('offres_repas')->updateOrInsert([
                        'repas_id' => $mealId,
                        'est_active' => true,
                    ], [
                        'annonce_id' => $announcementId,
                        'vendeur_id' => $seller,
                        'lieu_retrait_id' => $placeId,
                        'portions_restantes' => $data['portions_restantes'],
                        'limite_achat' => $deadline->toDateTimeString(),
                        'debut_retrait' => $start->toDateTimeString(),
                        'fin_retrait' => $end->toDateTimeString(),
                    ]);
                }

                return $announcementId;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                $disk->delete($path);
            }

            throw $exception;
        }

        $annonce = Annonce::query()->with(self::RELATIONS)->findOrFail($id);

        return (new AnnonceResource($annonce))->response()->setStatusCode($existing === null ? 201 : 200);
    }
}
