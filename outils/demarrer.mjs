import { spawn } from 'node:child_process'
import { existsSync } from 'node:fs'
import net from 'node:net'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const php = 'C:\\wamp64\\bin\\php\\php8.3.6\\php.exe'
const vite = path.join(root, 'frontend/node_modules/vite/bin/vite.js')
const router = path.join(
    root,
    'backend/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
)
const children = []
let closing = false

function listening(port) {
    return new Promise((resolve) => {
        const socket = net.createConnection({ host: '127.0.0.1', port })
        socket.setTimeout(700)
        socket.once('connect', () => {
            socket.destroy()
            resolve(true)
        })
        socket.once('error', () => {
            socket.destroy()
            resolve(false)
        })
        socket.once('timeout', () => {
            socket.destroy()
            resolve(false)
        })
    })
}

function stop(code = 0) {
    if (closing) return
    closing = true
    for (const child of children) child.kill()
    process.exitCode = code
}

function start(command, args, cwd, env = process.env) {
    const child = spawn(command, args, {
        cwd,
        env,
        stdio: 'inherit',
        windowsHide: true,
    })
    children.push(child)
    child.on('error', (error) => {
        console.error(error.message)
        stop(1)
    })
    child.on('exit', (code) => {
        if (!closing) {
            console.error(
                'Un serveur s’est arrêté. Relance cette commande après correction.',
            )
            stop(code || 1)
        }
    })
}

process.on('SIGINT', () => stop())
process.on('SIGTERM', () => stop())

if (![php, vite, router].every(existsSync)) {
    console.error('Outils ou dépendances manquants. Consulte ENVIRONNEMENT.md.')
    process.exitCode = 1
} else if (!(await listening(3306))) {
    console.error('Démarre MySQL dans WampServer, puis relance cette commande.')
    process.exitCode = 1
} else {
    if (!(await listening(8000))) {
        console.log('Démarrage de Laravel sur le port 8000…')
        start(
            php,
            [
                '-S',
                '127.0.0.1:8000',
                '-t',
                path.join(root, 'backend/public'),
                router,
            ],
            path.join(root, 'backend/public'),
            {
                ...process.env,
                PHPRC: path.join(root, '.local/php'),
                PHP_INI_SCAN_DIR: path.join(root, '.local/php/conf.d'),
            },
        )
    } else
        console.log(
            'Le port Laravel 8000 est déjà utilisé ; son serveur est conservé.',
        )

    if (!(await listening(5173))) {
        console.log('Démarrage de React sur le port 5173…')
        start(process.execPath, [vite], path.join(root, 'frontend'))
    } else
        console.log(
            'Le port React 5173 est déjà utilisé ; son serveur est conservé.',
        )

    console.log('\nHome Made : http://127.0.0.1:5173/')
    if (children.length > 0) {
        console.log(
            'Garde ce terminal ouvert. Ctrl+C arrête les serveurs lancés par cette commande.\n',
        )
    } else {
        console.log(
            'Les deux serveurs tournent déjà. Pour les arrêter, utilise leurs terminaux d’origine.\n',
        )
    }
}
