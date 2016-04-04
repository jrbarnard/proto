<?php

namespace App;

use Whoops\Exception\ErrorException;
use App\Exceptions\NotFoundException;
use App\Twig;

class Structure
{
    public static $json_filename = 'structure.json';
    public $json = ''; // stores the json structure as it comes (valid or not)
    public $routes = array(); // stores the routes object within structure
    public $uri = ''; // stores uri string e.g foo/bar
    public $uri_structure = array(); // stores uri in array e.g array('foo', 'bar')
    public $levels = 1; // stores number of uri levels in current request

    public $pages = array();


    protected function __construct()
    {
        $this->json = $this->getStructure();
        $this->routes = $this->convertJsonToAssocArr($this->json);

        // get and store uri info
        $this->uri = self::currentUri();
        $this->uri_structure = self::uriStructure($this->uri);
        $this->levels = count($this->uri_structure);

        // get the full request pages checking against the routes structure
        try {
            // search for and store current page
            $this->pages = $this->getRequestPages();
        } catch (NotFoundException $e) {
            // trigger 404
            http_response_code(404);
            $twig = Twig::init();
            $twig->loadTemplate('404');
            $twig->render();
            return;
        }

        // get current page
        $this->current_page = $this->pages[$this->levels - 1];
    }

    public static function init()
    {
        return new Structure();
    }

    /**
     * Gets the structure file contents
     * @return mixed
     * @throws ErrorException
     */
    private function getStructure()
    {
        if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . self::$json_filename)) {
            $json = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . self::$json_filename);

            if (false !== $json) {
                return $json;
            }
        }

        throw new ErrorException('Structure file: ' . self::$json_filename . ' not found in: ' . __DIR__);
    }

    /**
     * Converts Json to associative array of routes
     * @param $json
     * @return mixed
     * @throws ErrorException
     */
    private function convertJsonToAssocArr($json)
    {
        $arr = json_decode($json);

        if (null === $arr) {
            throw new ErrorException('Json in ' . self::$json_filename . ' is invalid and couldn\'t decode');
        }

        if (false === isset($arr->routes)) {
            throw new ErrorException('Json in ' . self::$json_filename . ' didn\'t have a valid routes object');
        }

        return $arr->routes;
    }

    /**
     * Method that gets uri string from get 'p' param
     * @return string
     */
    public static function currentUri()
    {
        if (empty($_GET['p'])) {
            $_GET['p'] = '/';
        }

        return $_GET['p'];
    }

    /**
     * Method that takes a string uri and converts to an array
     * e.g test/a/link => array('test', 'a', 'link');
     * @param $uri
     * @return array
     */
    public static function uriStructure($uri)
    {
        if (empty($uri)) {
            return array('/');
        }

        $parts = explode('/', $uri);

        // check for empty values, unset and then reindex
        foreach ($parts as $key => $value) {
            if ("" === $value) {
                unset($parts[$key]);
            }
        }

        $parts = array_values($parts);

        if (empty($parts)) {
            $parts = array('/');
        }

        return $parts;
    }

    /**
     * Method that gets all the request pages
     * @param bool $route
     * @return Page|bool
     * @throws NotFoundException
     */
    public function getRequestPages($route = false)
    {
        if (false === $route) {
            $route = $this->getTrailingRoute();
        }

        // store a pointer so we can walk to array
        $pointer = $this->routes;

        $pages = array();

        // iterate over pages
        for ($i = 0; $i < $this->levels; $i++) {
            if ($i > 0) {
                $pointer = isset($pointer->children) ? $pointer->children : array();
            }

            $pointer_page = $this->getRoute($this->uri_structure[$i], $pointer);
            // if there is a page on this level as part of the uri structure
            if (false !== $pointer_page) {

                $pages[] = new Page(array(
                    'uri' => $this->uri_structure[$i],
                    'page' => $pointer_page,
                    'level' => $i
                ));

                // there is a page so check if it is the one we're looking for
                if ($this->uri_structure[$i] === $route) {
                    return $pages;
                }
                // else set the pointer and iterate
                $pointer = $pointer_page;
            } else {
                throw new NotFoundException('Route not found', 404);
            }
        }
    }

    /**
     * Gets a route from a passed in routes array
     * Basically just gets by object arrow notation
     *
     * @param $route - route slug
     * @param $routes - array to check in
     *
     * @return route object or false if fails
     */
    private static function getRoute($route, $routes = array())
    {
        if (isset($routes->{$route})) {
            return $routes->{$route};
        } else {
            return false;
        }
    }

    /**
     * Simply gets the trailign route of the uri structure (i.e current route)
     * @return mixed
     */
    private function getTrailingRoute()
    {
        return $this->uri_structure[$this->levels - 1];
    }
}