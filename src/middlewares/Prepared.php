<?php

namespace slimExt\middlewares;

use slimExt\exceptions\InvalidConfigException;
use Slim;
use slimExt\base\Request;
use slimExt\base\Response;

class Prepared
{

    /**
     * Auth middleware invokable class
     *
     * @param  Request   $request  PSR7 request
     * @param  Response  $response PSR7 response
     * @param  callable  $next     Next middleware
     *
     * @return Response
     * @throws InvalidConfigException
     * @throws \InvalidArgumentException
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        Slim::config()->set('urls.route',$request->getUri()->getPath());

        return $next($request, $response);
    }
}