<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2016/2/19 0019
 * Time: 23:35
 */

namespace slimExt\web;

use inhere\exceptions\UnknownMethodException;

/**
 * Class RestFulController
 * @package slimExt\base
 *
 * how to use. e.g:
 *
 * ```
 * class Book extends slimExt\web\RestController
 * {
 *     public function getsAction($args)
 *     {}
 *     public function getAction($args)
 *     {}
 *     public function postAction($args)
 *     {}
 *     public function putAction($args)
 *     {}
 *     public function deleteAction($args)
 *     {}
 *     ... ...
 * }
 * ```
 *
 * in routes
 *
 * ```
 * $app->any('/api/test[/{action}]', api\Book::class);
 * ```
 */
abstract class RestController extends AbstractController
{
    const DEFAULT_ERR_CODE = 2;

    // $app->any('/api/test[/{action}]', api\Book::class);
    const RESOURCE_ARG_KEY = 'action';

    // match request like GET /users (get all resource)
    const MARK_MORE = '*';

    // allow multi request method reflect to one action.
    // e.g 'get|post' => 'index'
    const MULTI_SEP = '|';

    // method to action char
    // e.g 'get.search'
    const M2A_CHAR = '.';

    /**
     * method name suffix.
     * so, the access's real controller method name is 'method name' + 'suffix'
     * @var string
     */
    private $actionSuffix = 'Action';

    /**
     * @var array
     */
    protected $except = [];

    /**
     * allow more. like 'get*' 'head*'
     * @var array
     */
    private static $allowMore = [
        'get', 'head', 'options'
    ];

    /**
     * @return Response
     */
    public function headsAction()
    {
        return $this->response
            ->withHeader('X-Welcome', 'Hi, Welcome to the network.')
            ->withHeader('X-Request-Method', 'method heads');
    }

    /**
     * @param $id
     * @return Response
     */
    public function headAction($id = 0)
    {
        return $this->response
            ->withHeader('X-Welcome', 'Hi, Welcome to the network.')
            ->withHeader('X-Request-Method', 'method head')
            ->withHeader('X-Request-Param', $id);
    }

    /**
     * @return array
     */
    public function optionsAction()
    {
        return array_values($this->methodMapping());
    }

    /**
     * more information
     * @see \slimExt\base\AbstractController::doSecurityFilter()
     * @return array
     */
    public function filters()
    {
        return [
//            'access' => [
//                'filter' => AccessFilter::class,
//                'rules' => [
//                    [
//                        'actions' => ['login', 'error'],
//                        'allow' => true,
//                    ],
//                    [
//                        'actions' => ['logout', 'index'],
//                        'allow' => true,
//                        'roles' => ['@'],
//                    ],
//                ],
//            ]
        ];
    }

    /**********************************************************
     * controller method name handle
     **********************************************************/

    /**
     * method mapping - the is default mapping.
     * supported method:
     *     CONNECT DELETE GET HEAD OPTIONS PATCH POST PUT TRACE
     *
     * you can change it. e.g:
     * protected function methodMapping()
     * {
     *     return [
     *         'get*'   => 'index',   # GET /users
     *         'get'      => 'view',    # GET /users/1
     *         'post'     => 'create',  # POST /users
     *         'put'      => 'update',  # PUT /users/1
     *         'delete'   => 'delete',  # DELETE /users/1
     *         // ...
     *     ];
     *     // or
     *     // $mapping = parent::methodMapping();
     *     // $mapping['get'] = 'xxx';
     *     // return $mapping;
     * }
     * @return array
     */
    protected function methodMapping()
    {
        return [
            //REQUEST_METHOD => method name(no suffix)
            // 'gets' is special key.
            'get*' => 'gets',      # GET /users
            'get' => 'get',       # GET /users/1
            'post' => 'post',      # POST /users
            'put' => 'put',       # PUT /users/1
            # usually PUT == PATCH
            'patch' => 'patch',     # PATCH /users/1
            'delete' => 'delete',    # DELETE /users/1
            'head' => 'head',      # HEAD /users/1
            'head*' => 'heads',     # HEAD /users
            'options' => 'option',    # OPTIONS /users/1
            'options*' => 'options',   # OPTIONS /users

            'connect' => 'connect',   # CONNECT /users
            'trace' => 'trace',     # TRACE /users

            // multi REQUEST_METHOD match
            // 'get|post' => 'index'

            // extra method mapping
            // 'get.search' => search
            // 'post.save'  => save
        ];
    }

    /**
     * handleMethodMapping -- return real controller method name
     * @param  Request $request
     * @param array $args
     * @return string
     * @throws UnknownMethodException
     */
    private function handleMethodMapping($request, array $args)
    {
        // default restFul action name, equals to REQUEST_METHOD
        $method = strtolower($request->getMethod());
        $mapping = $this->methodMapping();

        if (!$mapping || !is_array($mapping)) {
            throw new UnknownMethodException('No any accessible resource method!');
        }

        $argument = !empty($args[self::RESOURCE_ARG_KEY]) ? trim($args[self::RESOURCE_ARG_KEY]) : '';

        // convert 'first-second' to 'firstSecond'
        if ($argument && strpos($argument, '-')) {
            $argument = ucwords(str_replace('-', ' ', $argument));
            $argument = str_replace(' ', '', lcfirst($argument));
        }

        $map = $this->parseMethodMapping($mapping);

        // match like 'get.search' extra method
        if ($argument) {
            $extraKey = $method . self::M2A_CHAR . $argument;

            if (isset($map[$extraKey])) {
                return [$map[$extraKey], null];
            }
        }

        $action = $error = '';
        $moreKey = $method . self::MARK_MORE;

        foreach ($map as $key => $value) {
            // like 'get*' 'head*'
            if (!$argument && $key === $moreKey && in_array($method, self::$allowMore, true)) {
                $action = $value;

                // have argument. like '/users/1' '/users/username'
            } else if ($key === $method) {
                $action = $method === 'options' ? 'option' : $value;
            }

            // match successful.
            if ($action) {
                break;
            }
        }

        return [$action, $error];
    }

//    private function getParsedMapping()
//    {
//    }

    /**
     * @param array $mapping
     * @return array
     */
    private function parseMethodMapping(array $mapping)
    {
        $map = [];

        foreach ($mapping as $key => $item) {
            $key = str_replace(' ', '', $key);
            $item = trim($item);

            // get.search get|post.search
            if (strpos($key, '.')) {
                list($m, $a) = explode('.', $key);

                $m = strtolower($m);

                if (strpos($m, '|')) {
                    foreach (explode('|', $m) as $k) {
                        $map[$k . self::M2A_CHAR . $a] = $item;
                    }
                } else {
                    $map[$m . self::M2A_CHAR . $a] = $item;
                }

                // get|post => index
            } elseif (strpos($key, '|')) {
                $key = strtolower($key);

                foreach (explode('|', $key) as $k) {
                    $map[$k] = $item;
                }
            } else {
                $key = strtolower($key);

                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**********************************************************
     * call the controller method
     **********************************************************/

    /**
     * e.g.
     * define route:
     *
     * ```
     *   $app->any('/test[/{resource}]', controllers\api\Test::class);
     * ```
     *
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    protected function processInvoke(array $args)
    {
        // default restFul action name
        list($action, $error) = $this->handleMethodMapping($this->request, $args);

        if ($error) {
            return $this->errorHandler($error);
        }

        $actionMethod = $action . ucfirst($this->actionSuffix);

        if (!method_exists($this, $actionMethod)) {
            // throw new NotFoundException('Error Processing Request, Action [' . $action . '] don\'t exists!');
            $error = 'Error Processing Request, Action [' . $action . '] don\'t exists!';

            return $this->response->withJson([], 330, $error, 403);
//          return $this->errorHandler($error);
        }

        // if enable request action security filter
        if (true !== ($result = $this->doSecurityFilter($action))) {
            return $result;
        }

        /** @var Response $response */
        $resp = $this->$actionMethod(array_shift($args));

        // if the action return is array data
        if (is_array($resp)) {
            $resp = $this->response->withJson($resp);
        }

        return $resp;
    }


    /**
     * @param string $error
     * @param int $code
     * @param int $status
     * @return mixed
     * @internal param Response $response
     */
    protected function errorHandler($error, $code = 2, $status = 403)
    {
        //throw new HttpRequestException($error);
        return $this->response->withJson([], $code, $error, $status);
    }
}