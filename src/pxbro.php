<?php
/**
 * PXBRO Console Interface
 */
class PXBRO extends Console_Abstract
{
    public const VERSION = 1;

    /**
     * Callable Methods
     */
    protected const METHODS = [
        'update',
    ];

    // Name of script and directory to store config
    protected const SHORTNAME = 'pxbro';

    public $sources = [];

    /**
     * Download XML for configured source
     * @param $source default to first configured
     */
    public function update($source=null, $cache=false)
    {
        $sources = array_keys($this->sources);
        $default_source = $sources[0];
        $source = $this->prepArg($source, $default_source);

        $source_config = $this->sources[$source];
        $url = $source_config['url'];

        $sourceObj = new PXBRO_Source($this, $source, $url);
        $sourceObj->clean_url = 'index';
        $sourceObj->readXML($cache);
        $sourceObj->saveHTML();
    }

    /**
     * Get Config Dir
     */
    public function getConfigDir()
    {
        if (is_null($this->config_dir))
        {
            $this->config_dir = '.' . DS . '.' . static::SHORTNAME;
        }

        return $this->config_dir;
    }

    /**
     * Init config defaults, then call parent
     */
    public function initConfig()
    {
        $config_dir = $this->getConfigDir();

        // Config defaults
        $this->sources = [
            'example' => [
                'url' => 'https://www.w3schools.com/xml/simple.xml'
            ],
        ];

        parent::initConfig();
    }

}
PXBRO::run($argv);
?>
