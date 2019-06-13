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
	protected $signature = 'postman:export {--api} {--web} {--url} {--port} {--name}';

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
			return $this->info("Please, specify the type of export with flags.\nYou can use --api or --web.");
		}

		if (!$this->option('name')) {$name = config('app.name') . '_postman';} else { $name = $this->option('name');}
		if (!$this->option('url')) {$url = '{{' . config('app.name') . 'URL}}';} else { $url = $this->option('url');}
		if (!$this->option('port')) {$port = '';} else { $url = $url . ':' . $this->option('port');}
		if ($this->option('api')) {$routeType = 'api';}
		if ($this->option('web')) {$routeType = 'web';}

		// Set the base data.
		$routes = [
			'variables' => [],
			'info'      => [
				'name'        => $name . '_' . $routeType,
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

				//GETTING @PARAMs @VARs @DESCRIPTIONs from PhpDoc comments
				$p = $this->getParams($route);

				//API ROUTES
				if ($this->option('api') && "api" == $route->middleware()[0]) {
					$routes['item'][] = [
						'name'     => $method . ' | ' . $route->uri(),
						'request'  => [
							'auth'        => '',
							'method'      => strtoupper($method),
							'header'      => [
								[
									'key'         => 'Content-Type',
									'value'       => 'application/json',
									'description' => $p['description'],
								],
							],
							'body'        => [
								'mode' => 'raw',
								'raw'  => '{\n    \n}',
							],
							'url'         => [
								'raw'   => $url . '/' . $route->uri(),
								'host'  => $url . '/' . $route->uri(),
								'query' => $p['paramsArray'],
							],
							'description' => $p['description'],
						],
						'response' => [],
					];
				}
				//WEB ROUTES
				else if ($this->option('web') && "web" == $route->middleware()[0]) {
					$routeType = 'web';

					$routes['item'][] = [
						'name'     => $method . ' | ' . $route->uri(),
						'request'  => [
							'url'         => $url . '/' . $route->uri(),
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

		if (!$this->files->put($name . '_' . $routeType . '.json', json_encode($routes))) {
			$this->error('Export failed');
		} else {
			$this->info('Routes exported!');
		}
	}

	public function getParams($route) {
		if (empty($route->action['controller'])) {return;}

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
				if (!empty(end($descriptions)[0])) {
					$description      = $this->cleanString(end($descriptions)[0]);
					$p['description'] = trim($description);
				} else { $p['description'] = '';}

				//@param
				preg_match_all("~@param(.*)~", $comment, $params, PREG_PATTERN_ORDER);
				if (!empty(end($params)[0])) {
					foreach ($params[1] as $key => $param) {
						$param = explode(' ', $this->cleanString($param));
						//type
						$p['paramsArray'][$key]['type'] = array_shift($param);
						//name
						$p['paramsArray'][$key]['key'] = array_shift($param);
						//description
						$p['paramsArray'][$key]['description'] = implode(' ', $param);
					}
				} else { $p['paramsArray'] = '';}

				//@var
				preg_match_all("~@var(.*)~", $comment, $vars, PREG_PATTERN_ORDER);
				if (!empty(end($vars)[0])) {
					foreach ($vars[1] as $key => $var) {
						$var = explode(' ', $this->cleanString($var));
						//type
						$p['varsArray'][$key]['type'] = array_shift($var);
						//name
						$p['varsArray'][$key]['key'] = array_shift($var);
						//description
						$p['varsArray'][$key]['description'] = implode(' ', $var);
					}
				} else { $p['varsArray'] = '';}

				//@return
				preg_match_all("~@return(.*)~", $comment, $returns, PREG_PATTERN_ORDER);
				if (!empty(end($returns)[0])) {
					$p['return'] = $this->cleanString($returns[1][0]);
				} else { $p['return'] = '';}
			}

			unset($param, $value, $description, $c);
		}

		if (!isset($p) || empty($p)) {
			$p['return']      = '';
			$p['response']    = '';
			$p['param']       = '';
			$p['description'] = '';
			$p['paramsArray'] = '';
			$p['varsArray']   = '';
		}
		return $p;
	}

	public function cleanString($string) {
		$string = str_replace('*', '', $string); // Replaces
		$string = str_replace('#', '', $string); // Replaces
		$string = str_replace('  ', '', $string); // Replaces
		if (substr($string, -1) == '@') {$string = substr_replace($string, "", -1);} // Removes last @
		$string = preg_replace('/[\n\t*]/', '', $string); // Removes special chars.
		return trim($string);
	}
};
