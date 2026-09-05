import { execFileSync } from 'node:child_process';

export default function globalSetup() {
    // --env=testing pins this destructive reset to gestor_comercial_saas_test.
    execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--env=testing', '--force'], {
        cwd: process.cwd(),
        env: { ...process.env, APP_ENV: 'testing' },
        stdio: 'inherit',
    });
}
