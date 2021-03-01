<?php


namespace Duxingyu\WorkTime\Providers;


use Duxingyu\WorkTime\Eloquent\WorkEndTimeCalculate;
use Duxingyu\WorkTime\Eloquent\WorkRestConfigEloquent;
use Duxingyu\WorkTime\Test\WorkConfig;
use Illuminate\Support\ServiceProvider;

class WorkTimeProviders extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true; //是否延时绑定


    /**
     * Bootstrap the application services.
     * 执行文案register后执行此方法
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // FileSystem.
        // 注册;
        $this->app->bind('WorkEndTimeCalculate', function ($app) {
            return new WorkEndTimeCalculate(WorkRestConfigEloquent::class);
        });

    }


}
