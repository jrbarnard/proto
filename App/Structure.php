<?php
namespace Proto;

use \Exception;
use Proto\Exceptions\NotFoundException;

/**
 * Class Structure
 * Deals with the json structure, gets it and uses the uri structure to locate the page we're on
 * @package App
 */
class Structure
{
    /**
     * @var string
     */
    public static $jsonFilename = 'structure.json';

    /**
     * @var string
     */
    public $structureLocation = __DIR__ . DIRECTORY_SEPARATOR;

    /**
     * stores the json structure as it comes (valid or not)
     * @var mixed|string
     */
    public $json = '';

    /**
     * stores the routes object within structure
     * @var array|mixed
     */
    public $routes = array();

    /**
     * stores uri string e.g foo/bar
     * @var string
     */
    public $uri = '';

    /**
     * stores uri in array e.g array('foo', 'bar')
     * @var array
     */
    public $uri_structure = array();

    /**
     * stores number of uri levels in current request
     * @var int
     */
    public $levels = 1;

    /**
     * records if we've hit a 404 or not
     * @var bool
     */
    public $is404 = false;

    /**
     * To store array of pages for each point in the route
     * @var Page|array|bool
     */
    public $pages = array();

    /**
     * Structure constructor.
     */
    protected function __construct()
    {
        $this->structureLocation = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        // get and convert json structure to an assoc arr
        $this->json = $this->getStructure();
        $this->routes = $this->convertJsonToAssocArr($this->json);

        /**
         * get and store uri info (store 3 bits of information on it):
         * actual route: /test/a/uri
         * array of routes: ['test', 'a', 'route']
         * levels in routes, simply a count of the array
         */
        $this->uri = self::currentUri();
        $this->uri_structure = self::uriStructure($this->uri);
        $this->levels = count($this->uri_structure);

        /**
         * get the full request pages checking against the routes structure
         * If we hit a 404 (page can't be found), we will catch the exception thrown and set the page to 404.
         */
        try {
            // search for and store current pages
            $this->pages = $this->getRequestPages();
        } catch (NotFoundException $e) {
            // trigger 404
            http_response_code(404);
            $this->is404 = true;
        }

        /**
         * By not just existing and setting up the 404 page as standard, we get to handle the 404 through twig.
         * Else just set up current page
         */
        if (false === $this->is404) {
            $this->current_page = $this->pages[$this->levels - 1];
        } else {
            $this->current_page = Page::page404();
        }
    }

    /**
     * Static method for initialising an instance of structure
     * @return Structure
     */
    public static function init()
    {
        return new Structure();
    }

    /**
     * Gets the structure file contents
     * @return mixed
     * @throws Exception
     */
    private function getStructure()
    {
        if (file_exists($this->structureLocation . self::$jsonFilename)) {
            $json = file_get_contents($this->structureLocation . self::$jsonFilename);

            if (false !== $json) {
                return $json;
            }
        }

        throw new Exception('Structure file: ' . self::$jsonFilename . ' not found in: ' . $this->structureLocation);
    }

    /**
     * Converts Json to associative array of routes
     * @param $json
     * @return mixed
     * @throws Exception
     */
    private function convertJsonToAssocArr($json)
    {
        $arr = json_decode($json, true);

        if (null === $arr) {
            throw new Exception('Json in ' . self::$jsonFilename . ' is invalid and couldn\'t decode');
        }

        if (false === isset($arr['routes'])) {
            throw new Exception('Json in ' . self::$jsonFilename . ' didn\'t have a valid routes param');
        }

        return $arr['routes'];
    }

    /**
     * Method that gets uri string
     * @return string
     */
    public static function currentUri()
    {
        $url = parse_url($_SERVER['REQUEST_URI']);
        $path = isset($url['path']) && !empty($url['path']) && $url['path'] !== '/' ? mb_substr($url['path'], 1) : '/';
        return $path;
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
                $pointer = isset($pointer['children']) ? $pointer['children'] : array();
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
     * @return route item or false if fails
     */
    private static function getRoute($route, $routes = array())
    {
        if (isset($routes[$route])) {
            return $routes[$route];
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