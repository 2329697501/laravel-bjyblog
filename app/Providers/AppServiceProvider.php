<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Config;
use App\Models\FriendshipLink;
use App\Models\GitProject;
use App\Models\Tag;
use File;
use Cache;
use App\Observers\CacheClearObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //分配前台通用的数据
        view()->composer('home/*', function($view){
            $assign = Cache::remember('common', 10080, function () {
                // 获取分类导航
                $category = Category::select('id', 'name')->get();

                // 获取标签下的文章数统计
                $tagModel = new Tag();
                $tag = $tagModel->getArticleCount();

                // 获取置顶推荐文章
                $topArticle = Article::select('id', 'title')
                    ->where('is_top', 1)
                    ->orderBy('created_at', 'desc')
                    ->get();

                // 获取最新评论
                $commentModel = new Comment();
                $newComment = $commentModel->getNewData();

                // 获取友情链接
                $friendshipLink = FriendshipLink::select('name', 'url')->orderBy('sort')->get();

                // 获取开源项目
                $gitProject = GitProject::select('name', 'type')->orderBy('sort')->get();
                $data = compact('category', 'tag', 'topArticle', 'newComment', 'friendshipLink', 'gitProject');
                return $data;
            });

            $view->with($assign);
        });

        // 分配全站通用的数据
        view()->composer('*', function ($view) {
            // 获取配置项
            $config = Cache::remember('config', 10080, function () {
                return Config::pluck('value','name');
            });
            $assign = [
                'config' => $config
            ];
            // 获取赞赏捐款文章
            if (!empty($config['QQ_QUN_ARTICLE_ID'])) {
                $qqQunArticle = Cache::remember('qqQunArticle', 10080, function () {
                    return Article::select('id', 'title')->first();
                });
                $assign['qqQunArticle'] = $qqQunArticle;
            }
            $view->with($assign);
            // 用 config 表中的配置项替换 /config/ 目录下文件中的配置项
            $serviceConfig = [
                'services.github.client_id' => $config['GITHUB_CLIENT_ID'],
                'services.github.client_secret' => $config['GITHUB_CLIENT_SECRET'],

                'services.qq.client_id' => $config['QQ_APP_ID'],
                'services.qq.client_secret' => $config['QQ_APP_KEY'],

                'services.weibo.client_id' => $config['SINA_API_KEY'],
                'services.weibo.client_secret' => $config['SINA_SECRET'],
            ];
            config($serviceConfig);
        });

        // 获取所有的模型文件
        $modelPath = File::allFiles(app_path('Models'));
        foreach ($modelPath as $v) {
            // 获取模型文件的BaseName
            $baseName = $v->getBaseName('.php');
            // 如果是 Base Model 则跳过
            if ($baseName === 'Base') {
                continue;
            }
            // 补全模型的命名空间
            $model = '\App\Models\\'.$baseName;
            // 注册观察者
            $model::observe(CacheClearObserver::class);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 逆向迁移
        if ($this->app->environment() !== 'production') {
            $this->app->register(\Way\Generators\GeneratorsServiceProvider::class);
            $this->app->register(\Xethron\MigrationsGenerator\MigrationsGeneratorServiceProvider::class);
            // laravel-ide-helper ide支持
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
    }
}
