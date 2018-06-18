<?php
/**
 * PXBRO Source
 */
class PXBRO_Source
{
    public $slug = '';
    public $url = '';
    public $filename = 'index';
    public $cache = false;

    public $title = null;
    public $raw_xml = null;
    public $xml = null;

    protected $shell = null;

    /**
     * Construct new source processor
     * @param $shell - instance of console abstract
     * @param $slug - source slug
     * @param $url - url to download from
     * @param $cache - true to read from cache if available - for debugging/testing
     */
    public function __construct($shell, $slug, $url, $cache=false)
    {
        $this->shell = $shell;

        $this->slug = $slug;
        $this->url = $url;

        $this->cache = $cache;
    }

    /**
     * Read XML - from URL
     */
    public function readXML()
    {
        $this->log("readXML $this->url, $this->slug");

        $cache_file = self::getCacheFile($this->url, $this->slug);

        if ($this->cache and is_file($cache_file))
        {
            $this->log("Reading from cache file: $cache_file");
            $this->raw_xml = file_get_contents($cache_file);
        }
        else
        {
            $this->log("Downloading and saving XML to: $cache_file");
            $ch = $this->getCurl($this->url);
            $response = curl_exec($ch);

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->log($header);

            $this->raw_xml = $body;

            curl_close($ch);
            if (!empty($this->raw_xml))
            {
                file_put_contents($cache_file, $this->raw_xml);
            }
        }

        $this->xml = new SimpleXMLElement($this->raw_xml);

        $this->log('readXML complete');
    }

    /**
     * Save to HTML using template
     */
    public function saveHTML($name=false)
    {
        $this->log('saveHTML');

        $html_dir = __DIR__ . DS . ".." . DS . "output" . DS . "html" . DS . $this->slug;
        if (!is_dir($html_dir))
            mkdir($html_dir, 0777, true);
        $name = empty($name) ? self::getCleanURL($url) : $name;
        $html_file = $html_dir . DS . $name . '.html';

        $template_dir = "templates" . DS . $this->slug;
        $template_file = $template_dir . DS . $this->filename . '.phtml';
        if (!is_file($template_file))
        {
            $this->error("Template file doesn't exist: $template_file");
        }

        $this->more_xml = [];

        $this->title = ucwords($this->slug);

        // Parse main template first, so it can set variables if needed
        ob_start();
        require($template_file);
        $body = ob_get_clean();

        ob_start();
        @include($template_dir . DS . 'header.phtml');
        echo $body;
        @include($template_dir . DS . 'footer.phtml');
        $html = ob_get_clean();

        file_put_contents($html_file, $html);

        $this->output('Fetching extra XML');
        $total = count($this->more_xml);
        if ($total > 0)
        {
            $this->outputProgress(0, $total);
            foreach ($this->more_xml as $u => $url)
            {
                $this->outputProgress($u+1, $total);
                $source = new PXBRO_Source($this->shell, 'cat', $url, $this->cache);
                $source->readXML();
            }
        }

        $this->log('saveHTML complete');
    }

    /**
     * Get cleaned version of URL for use as filename/path
     */
    public static function getCleanURL($url)
    {
        $clean_url = $url;
        $clean_url = strtolower($clean_url);
        $clean_url = preg_replace('/https?:\/\//', '', $clean_url);
        $clean_url = preg_replace('/[^0-9a-z]+/', '-', $clean_url);
        return $clean_url;
    }

    /**
     * Get cache file for a given URL
     */
    public static function getCacheFile($url, $slug)
    {
        $cache_dir = __DIR__ .  DS . ".." . DS . "output" . DS . "xml" . DS . $slug;
        if (!is_dir($cache_dir))
            mkdir($cache_dir, 0777, true);

        return $cache_dir . DS . self::getCleanURL($url) . '.xml';
    }

    /**
     * Pass through functions for shell
     */
    public function __call($method, $arguments)
    {
        $shell_call = [$this->shell, $method];
        if (is_callable($shell_call))
        {
            return call_user_func_array($shell_call, $arguments);
        }
    }

}
