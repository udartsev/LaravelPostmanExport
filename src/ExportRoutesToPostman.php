<?php

namespace udartsev\LaravelPostmanExport;

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
			//if ($this->option('name')) {$name = $this->option('name');} else { $name = config('app.name') . '_postman';}

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

					if ($this->option('url')) {
						$url = $this->option('url') . ':' . $port . '/' . $route->uri();
					} else { $url = url(':' . $port . '/' . $route->uri());}

					$p = $this->getParams($route);
					if (!isset($p['description'])) {$p['description'] = '';}

					//API ROUTES
					if ($this->option('api') && "api" == $route->middleware()[0]) {
						$routes['item'][] = [
							'name'     => $method . ' | ' . $route->uri(),
							'request'  => [
								'auth'   => '',
								'method' => strtoupper($method),
								'header' => [
									[
										'key'         => 'Content-Type',
										'value'       => 'application/json',
										'description' => '',
									],
								],
								'body'   => [
									'mode' => 'raw',
									'raw'  => '{\n    \n}',
								],
								'url'    => [
									'raw'   => $url,
									'query' => $p['paramsArray'],
								],
								//'description' => $p['description'],
							],
							'response' => [],
						];
					}
					//WEB ROUTES
					else if ($this->option('web') && "web" == $route->middleware()[0]) {
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

					unset($p, $paramsArray);
				}
			}

			if (!$this->files->put($name . '.json', json_encode($routes))) {
				$this->error('Export failed');
			} else {
				$this->info('Routes exported!');
			}
		}
	}

	private function getParams($route) {
		$controller = $route->action['controller'];

		$file = str_replace('\\', '/', $controller);
		$file = explode('/', $file);
		if ('App' !== $file[0]) {
			array_unshift($file, 'vendor');
		} else {
			$file[0] = 'app';
		}
		$file = base_path() . '/' . implode('/', $file);
		$file = strstr($file, '@', TRUE) . '.php';

		try {@$file_open = fopen($file, "r");} catch (Exception $e) {;}

		/**
		 * @dev Reading file and search comments
		 */
		if ($file_open) {
			$file_string = fread($file_open, filesize($file));

			// getting function name
			$function_name = explode('@', $controller);
			$function_name = $function_name[1];
			$route_part    = substr($file_string, 0, mb_strpos($file_string, $function_name));

			if (null != $route_part) {
				// getting commented strokes for function
				preg_match_all("~//?\s*\*[\s\S]*?\*\s*//?~m", $route_part, $comments, PREG_OFFSET_CAPTURE);
				$comment = end($comments[0])[0];

				//@description
				preg_match_all("~@description([\s\S]*? )([\s\S]*?)\\@~", $comment, $descriptions, PREG_PATTERN_ORDER);
				foreach ($descriptions as $key => $description) {
					if (!empty($description) && preg_match("~@description~", $description[0])) {
						$description      = $this->cleanString($description[0]);
						$p['description'] = trim(str_replace('@description', '', $description));
					}
				}

				//@param
				$c = 0;
				preg_match_all("~@param([\s\S]*? )([\s\S]*?)\\@~", $comment, $params, PREG_PATTERN_ORDER);
				foreach ($params as $key => $param) {
					if (!empty($param) && preg_match("~@param~", $param[0])) {
						$param                       = $this->cleanString($param[0]);
						$param                       = trim(str_replace('@param', '', $param));
						$param                       = explode('-', $param);
						$p['paramsArray'][$c]['key'] = $param[0];
						if (isset($param[1])) {
							$p['paramsArray'][$c]['description'] = trim($param[1]);
						} else { $p['paramsArray'][$c]['description'] = '';}
						$c++;
					}
				}

				//@return
				preg_match_all("~@return([\s\S]*? )([\s\S]*?)\\@~", $comment, $returns, PREG_PATTERN_ORDER);
				foreach ($returns as $key => $return) {
					if (!empty($return) && preg_match("~@return~", $return[0])) {
						$return        = $this->cleanString($return[0]);
						$p['response'] = trim(str_replace('@return', '', $value));
					}
				}
			}

			unset($param, $value, $description, $c);
		}

		if (!isset($p) || empty($p)) {
			$p['key']         = '';
			$p['value']       = '';
			$p['response']    = '';
			$p['param']       = '';
			$p['description'] = '';
			$p['paramsArray'] = [];
		}

		return $p;
	}

	private function cleanString($string) {
		$string = str_replace('*', '', $string); // Replaces
		$string = str_replace('#', '', $string); // Replaces
		$string = str_replace('  ', '', $string); // Replaces
		if (substr($string, -1) == '@') {$string = substr_replace($string, "", -1);} // Removes last @
		return preg_replace('/[^A-Za-z0-9\ @-]/', '', $string); // Removes special chars.
	}
}

