<?php

/**
 * Copyright (c) 2010-2017 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Middleware\StaticMiddleware;

use Eureka\Component\Config\Config;
use Eureka\Component\Container\Container;
use Eureka\Component\Psr\Http\Middleware\DelegateInterface;
use Eureka\Component\Psr\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Abstract Class Static Middleware
 *
 * @author  Romain Cottard
 * @version 1.0.0
 */
abstract class StaticMiddlewareAbstract implements ServerMiddlewareInterface
{
    /**
     * @var Config|null $config Config
     */
    private $config = null;

    /**
     * CssMiddleware constructor.
     *
     * @param Config $config
     * @param string $path
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $frame
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $frame)
    {
        $response = $frame->next($request);

        return $this->readFile($request, $response);
    }

    /**
     * Get Mime Type
     *
     * @param  string $file
     * @return string
     */
    protected function getMimeType($file)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, $file);
    }

    /**
     * Read & add content file to the response.
     *
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     * @return ResponseInterface
     */
    protected function readFile(ServerRequestInterface $request, ResponseInterface $response)
    {
        $path = trim($request->getQueryParams()['file']);
        $ext  = trim($request->getQueryParams()['ext']);

        //~ Uri form: cache/{theme}/{package}/{module}/{type}/{filename}
        $pattern = '`(cache)/([a-z0-9_-]+)/([a-z0-9_-]+)/([a-z0-9_-]+)/([a-z]+)/([a-z0-9_./-]+)`i';
        $matches = [];

        if (!(bool) preg_match($pattern, $path, $matches)) {
            throw new \Exception('Invalid image uri');
        }

        $cache    = $matches[1];
        $theme    = $matches[2];
        $package  = $matches[3];
        $module   = $matches[4];
        $type     = $matches[5];
        $filename = $matches[6];

        $basePath = $this->config->get('global.dir.root') . '/vendor/eureka';
        $file = $basePath . '/theme-' . $theme . '-' . $package . '/src/static/' . $module . '/' . $type . '/' . $filename . '.' . $ext;

        if (!file_exists($file)) {
            throw new \Exception('File does not exists ! (file: ' . $file . ')');
        }

        $content = file_get_contents($file);

        //~ Write file in cache when is on prod
        if ('prod' === $this->config->getEnvironment()) {
            $this->writeCache(dirname($path), $filename . '.' . $ext, $content);
        }

        $response = $response->withHeader('Content-Type', $this->getMimeType($file));
        $response->getBody()->write($content);

        return $response;
    }

    /**
     * Write cache
     *
     * @param  string $file Cache file
     * @param  string $content File content
     * @return void
     */
    private function writeCache($path, $filename, $content)
    {
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            throw new \Exception('Unable to create directory');
        }

        file_put_contents($path . '/' . $filename, $content);
    }
}