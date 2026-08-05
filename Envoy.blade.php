@setup
    if (file_exists(__DIR__.'/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
    }

    $server = $server
    ?? $_ENV['DEPLOY_SERVER']
    ?? getenv('DEPLOY_SERVER')
    ?: null;

    if (is_string($server)) {
    $server = trim($server);
    // Envoy already runs `ssh`; strip accidental "ssh " prefixes.
    $server = preg_replace('/^ssh\s+/i', '', $server);
    }

    $missingServer = empty($server);
    $server = $server ?: '127.0.0.1';

    $path = $path
    ?? $_ENV['DEPLOY_PATH']
    ?? getenv('DEPLOY_PATH')
    ?: '/var/www/chetnasharm-backend-L';

    $branch = $branch
    ?? $_ENV['DEPLOY_BRANCH']
    ?? getenv('DEPLOY_BRANCH')
    ?: 'mahmudul';

    $php = $php
    ?? $_ENV['DEPLOY_PHP']
    ?? getenv('DEPLOY_PHP')
    ?: 'php';
@endsetup

@servers(['web' => $server])

@story('deploy')
    pull
    migrate
    optimize
@endstory

@story('deploy-fresh')
    pull
    migrate-fresh
    optimize
@endstory

@task('pull', ['on' => 'web'])
    @if ($missingServer)
        echo "Set DEPLOY_SERVER in .env (e.g. user@your-server) or pass --server=user@host"
        exit 1
    @endif

    echo "Deploying branch {{ $branch }} to {{ $path }}"
    cd {{ $path }}
    git fetch origin
    git checkout {{ $branch }}
    git pull origin {{ $branch }}
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
@endtask

@task('migrate', ['on' => 'web'])
    cd {{ $path }}
    {{ $php }} artisan migrate --force
@endtask

@task('migrate-fresh', ['on' => 'web'])
    cd {{ $path }}
    {{ $php }} artisan migrate:fresh --seed --force
@endtask

@task('optimize', ['on' => 'web'])
    cd {{ $path }}
    {{ $php }} artisan optimize:clear
    {{ $php }} artisan config:cache
    {{ $php }} artisan route:cache
    {{ $php }} artisan view:cache
    {{ $php }} artisan queue:restart
@endtask
