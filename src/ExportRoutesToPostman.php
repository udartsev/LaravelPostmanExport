<?php

namespace RLStudio\Laraman;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Ramsey\Uuid\Uuid;

class ExportRoutesToPostman extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'postman:export {--name=postman_collection} {--api} {--web} {--url=https://localhost} {--port=8000}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Export all routes to a json file that can be imported in Postman';

	/**
	 * The Laravel router.
	 *
	 * @var \Illuminate\Routing\Router
	 */
	private $router;

	/**
	 * The filesystem implementation.
	 *
	 * @var \Illuminate\Contracts\Filesystem\Filesystem
	 */
	private $files;

	/**
	 * Create a new command instance.
	 *
	 * @param \Illuminate\Routing\Router $router
	 * @param \Illuminate\Contracts\Filesystem\Filesystem $files
	 */
	public function __construct(Router $router, Filesystem $files) {
		$this->router = $router;
		$this->files  = $files;
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		if (!$this->option('api') && !$this->option('web')) {
			$this->info("Please, specify the type of export with flags.\nYou can use --api or --web.");
		} else {
			$name = $this->option('name');
			$port = $this->option('port');
			$url  = $this->option('url');

			// Set the base data.
			$routes = [
				'variables' => [],
				'info'      => [
					'name'        => $name,
					'_postman_id' => Uuid::uuid4(),
					'description' => '',
					'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
				],
			];

			foreach ($this->router->getRoutes() as $route) {
				foreach ($route->methods as $method) {
					if ('HEAD' == $method) {
						continue;
					}
					//dd($route->parameters);
					//dump($route->parameters);

					if ($this->option('api') && "api" == $route->middleware()[0]) {
						if ($this->option('url')) {
							$url = $this->option('url') . ':' . $port . '/' . $route->uri();
						} else {
							$url = url(':' . $port . '/' . $route->uri());
						}

						$routes['item'][] = [
							'name'     => $method . ' | ' . $route->uri(),
							'request'  => [
								'url'         => $url,
								/*'params'      => [
								'key'         => '',
								'value'       => '',
								'description' => '',
								],*/
								'method'      => strtoupper($method),
								'header'      => [
									[
										'key'         => 'Content-Type',
										'value'       => 'application/json',
										'description' => '',
									],
								],
								'body'        => [
									'mode' => 'raw',
									'raw'  => '{\n    \n}',
								],
								'description' => '',
							],
							'response' => [],
						];
					}
					if ($this->option('web') && "web" == $route->middleware()[0]) {
						$routes['item'][] = [
							'name'     => $method . ' | ' . $route->uri(),
							'request'  => [
								'url'         => url(':' . $port . '/' . $route->uri()),
								'params'      => [
									'key'         => '',
									'value'       => '',
									'description' => '',
								],
								'method'      => strtoupper($method),
								'header'      => [
									[
										'key'         => 'Content-Type',
										'value'       => 'text/html',
										'description' => '',
									],
								],
								'body'        => [
									'mode' => 'raw',
									'raw'  => '{\n    \n}',
								],
								'description' => '',
							],
							'response' => [],
						];
					}
				}
			}
			if (!$this->files->put($name . '.json', json_encode($routes))) {
				$this->error('Export failed');
			} else {
				$this->info('Routes exported!');
			}
		}
	}
}
