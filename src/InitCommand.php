<?php

namespace ivampiresp\LaravelInitCommand;

use Illuminate\Console\Command;

class InitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'init {type=web} {--host=0.0.0.0} {--port=8000} {--queue=default} {--workers=1} {--name=default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '初始化应用程序（用于容器启动时）以及启动 Web 服务。 {type} 参数有 web 和 queue 两种，分别用于启动 Web 服务和队列服务。';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');

        // 检查是否已经初始化
        $lock = storage_path('init.lock');

        if (file_exists($lock)) {
            // 检测文件创建时间，大于 5 分钟则删除。
            $time = filemtime($lock);
            if (time() - $time > 300) {
                unlink($lock);
            } else {
                $this->warn("另一个初始化进程正在运行中。如果确定没有其他进程在运行，请手动删除 {$lock} 文件。");
            }

            $this->warn('正在等待另一个进程初始化完成。');
            // 一直等待锁文件被删除
            while (file_exists($lock)) {

                // 如果初始化时间过长，超过 5 分钟，则删除锁文件
                if (time() - $time > 300) {
                    unlink($lock);
                    break;
                }

                sleep(1);
            }


            // 如果 type=web
            if ($type === 'web') {

                $this->startWeb();
            }

            return 0;
        }

        $this->info('上锁。');
        // 加锁
        file_put_contents($lock, '');

        // 检测有无 .env
        if (!file_exists(base_path('.env'))) {
            // 复制 .env.example
            $this->info('复制 .env.example 为 .env');
            copy(base_path('.env.example'), base_path('.env'));
        }

        // 检测是否有 APP_KEY
        $APP_KEY = config('app.key', env('APP_KEY'));
        if (empty($APP_KEY)) {
            // 初始化
            $this->error('你还没有生成应用程序密钥。但是这个命令是用于 Kubernetes 容器启动时执行的，接下来将生成一个密钥，请手动保存它并将它映射到 Pod 中。');
            $this->call('key:generate', [
                '--force' => true,
                '--show' => true,
            ]);

            return 1;
        }

        $this->info('初始化 storage 目录。');
        // 初始化 storage 目录
        $this->initStorageDir();

        $this->info('初始化数据库。');

        // force migrate
        $this->call('migrate', [
            '--force' => true,
        ]);

        $this->info('生成缓存。');
        $this->call('optimize');

        $this->info('解锁');
        // 解锁
        unlink($lock);

        // 输出
        $this->info('应用程序初始化完成。');

        if ($type === 'web') {
            $this->info('启动 Web 服务。');
            $this->startWeb();
        } else {
            $this->info('启动队列服务。');
            $this->startQueue();
        }

        return 0;
    }

    private function initStorageDir(): void
    {
        // 检测 storage 下的目录是否正确
        $storage = storage_path();

        // 有无 app 目录
        if (!is_dir($storage . '/app')) {
            mkdir($storage . '/app');

            // 有无 public 目录
            if (!is_dir($storage . '/app/public')) {
                mkdir($storage . '/app/public');
            }
        }

        // 有无 framework 目录
        if (!is_dir($storage . '/framework')) {
            mkdir($storage . '/framework');

            // 有无 cache 目录
            if (!is_dir($storage . '/framework/cache')) {
                mkdir($storage . '/framework/cache');
            }

            // 有无 sessions 目录
            if (!is_dir($storage . '/framework/sessions')) {
                mkdir($storage . '/framework/sessions');
            }

            // 有无 testing 目录
            if (!is_dir($storage . '/framework/testing')) {
                mkdir($storage . '/framework/testing');
            }

            // 有无 views 目录
            if (!is_dir($storage . '/framework/views')) {
                mkdir($storage . '/framework/views');
            }
        }

        // 有无 logs 目录
        if (!is_dir($storage . '/logs')) {
            mkdir($storage . '/logs');
        }
    }

    public function startWeb()
    {
        $this->call('octane:start', [
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
            '--workers' => $this->option('workers'),
        ]);
    }

    public function startQueue()
    {
        $this->call('queue:work', [
            '--queue' => $this->option('queue'),
            '--name' => $this->option('name'),
        ]);
    }
}
