<?php namespace Mikhailkozlov\RetsLaravel;

use Illuminate\Support\ServiceProvider,
    Mikhailkozlov\RetsLaravel\Rets\RetsRepository,
    Mikhailkozlov\RetsLaravel\Rets\DummyRetsRepository,
    Mikhailkozlov\RetsLaravel\Console\SetupCommand,
    Mikhailkozlov\RetsLaravel\Console\InstallCommand,
    Mikhailkozlov\RetsLaravel\Console\UpdateCommand,
    Mikhailkozlov\RetsLaravel\Console\InitCommand,
    Mikhailkozlov\RetsLaravel\Console\FetchImages;

class RetsLaravelServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('mikhailkozlov/rets-laravel');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
        $this->registerRest();
        $this->registerCommands();

	}

    public function registerRest()
    {
        $app = $this->app;

        //$app->bind('Mikhailkozlov\RetsLaravel\Rets\RetsInterface', function($app)
        $app->bind('rets', function ($app) {
            if($app->environment('local')){
                return new DummyRetsRepository($app['events'], $app['config']);
            }
            return new RetsRepository($app['events'], $app['config']);
        });
    }

    /**
     * Register console commands rets:install
     * Register console commands rets:setup
     * Register console commands rets:update
     *
     * @author Mikhail Kozlov
     * @link   http://mikhailkozlov.com
     *
     * @return void
     */
    public function registerCommands()
    {
        $this->app['command.rets.install'] = $this->app->share(function()
            {
                return new InstallCommand();
            });

        $this->app['command.rets.setup'] = $this->app->share(function()
            {
                return new SetupCommand();
            });

        $this->app['command.rets.init'] = $this->app->share(function()
            {
                return new InitCommand();
            });

        $this->app['command.rets.update'] = $this->app->share(function()
            {
                return new UpdateCommand();
            });

        $this->app['command.rets.images'] = $this->app->share(function()
            {
                return new FetchImages();
            });

        $this->commands(
            'command.rets.install',
            'command.rets.setup',
            'command.rets.init',
            'command.rets.update',
            'command.rets.images'
        );
    }



    /**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
        return array('rets', 'rets.storage');
	}

}
