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
    public $clean_url = null;

    protected $shell = null;
    protected $raw_xml = null;
    protected $xml = null;

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
    public function readXML($cache=false)
    {
        $this->log('readXML');

        $cache_dir = "output" . DS . "xml" . DS . $this->slug;
        if (!is_dir($cache_dir))
            mkdir($cache_dir, 0777, true);

        $cache_file = $cache_dir . DS . $this->getCleanURL() . '.xml';

        if ($cache and is_file($cache_file))
        {
            $this->log("Reading from cache file: $cache_file");
            $this->raw_xml = file_get_contents($cache_file);
        }
        else
        {
            $this->log("Downloading and saving XML to: $cache_file");
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->url,
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_CONNECTTIMEOUT => 0,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $response = curl_exec($ch);

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->log($header);

            $this->raw_xml = $body;

            curl_close($ch);
            file_put_contents($cache_file, $this->raw_xml);
        }

        $this->xml = new SimpleXMLElement($this->raw_xml);

        $this->log('readXML complete');
    }

    /**
     * Save to HTML using template
     */
    public function saveHTML()
    {
        $this->log('saveHTML');

        $html_dir = "output" . DS . "html" . DS . $this->slug;
        if (!is_dir($html_dir))
            mkdir($html_dir, 0777, true);
        $html_file = $html_dir . DS . $this->getCleanURL() . '.html';

        $template_dir = "templates" . DS . $this->slug;
        $template_file = $template_dir . DS . $this->filename . '.phtml';
        if (!is_file($template_file))
        {
            $this->error("Template file doesn't exist: $template_file");
        }

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

        $this->log('saveHTML complete');
    }

    /**
     * Get cleaned version of URL for use as filename/path
     */
    public function getCleanURL()
    {
        if (is_null($this->clean_url))
        {
            $clean_url = $this->url;
            $clean_url = strtolower($clean_url);
            $clean_url = preg_replace('/https?:\/\//', '', $clean_url);
            $clean_url = preg_replace('/[^0-9a-z]+/', '-', $clean_url);
            $this->clean_url = $clean_url;
        }

        return $this->clean_url;
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
