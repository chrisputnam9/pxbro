<?php
/**
 * PXBRO Console Interface
 */
class PXBRO extends Console_Abstract
{
    const VERSION = 1;

    // Name of script and directory to store config
    const SHORTNAME = 'pxbro';

    /**
     * Callable Methods
     */
    protected static $METHODS = [
        'update',
        'update_cat',
        'update_clopay',
        'sync',
    ];

    protected $__sources = ["Source definitions - set in config file", "array"];
    public $sources = [];

    protected $__cache = "Whether to use cached XML - for debugging/dev";
    public $cache = false;

    protected $__sync = ["Git SSH URL to sync output data", "string"];
    public $sync = '';

    protected $___update = [
        "Download XML for configured source",
        ["Source to update - defaults to first configured source", "string"],
    ];
    public function update($source=null)
    {
        $sources = array_keys($this->sources);
        $default_source = $sources[0];
        $source = $this->prepArg($source, $default_source);

        if ($source == 'cat')
        {
            return $this->update_cat();
        }
        elseif ($source == 'clopay')
        {
            return $this->update_clopay();
        }

        $this->log("update - $source");
        $source_config = $this->sources[$source];
        $url = $source_config['url'];

        $sourceObj = new PXBRO_Source($this, $source, $url, $this->cache);
        $sourceObj->readXML();
        return $sourceObj->saveHTML('index');
    }

    protected $___update_cat = "Custom funtion to update cat specifically";
    public function update_cat()
    {
        $source = 'cat';
        $this->log("update - $source");
        $source_config = $this->sources['cat'];
        $url = $source_config['url'];
        $sourceObj = new PXBRO_Source($this, $source, $url, $this->cache);

        $this->output("Fetching classes from $url");
        $ch = $this->getCurl($url);
        $response = curl_exec($ch);
        if (preg_match_all('/cid\=(\d+)\D/', $response, $matches))
        {
            $class_ids = array_unique($matches[1]);
            $data = new StdClass();
            $data->classes=array();
            $total = count($class_ids);
            $c=0;
            $this->outputProgress($c, $total);
            foreach ($class_ids as $class_id)
            {
                $c++;
                $this->outputProgress($c, $total);
                $this->log("Fetching xml for class $class_id");
                $classSource = new PXBRO_Source($this, $source, "https://cpc.cat.com/ws/xml/US/{$class_id}tree_en.xml", $this->cache);
                $classSource->readXML();
                $data->classes[$class_id] = $classSource->xml;
            }

            $sourceObj->xml = $data;
            return $sourceObj->saveHTML('index');
        }

        $this->error('No classes found to fetch');
    }

    protected $___update_clopay = "Custom funtion to update clopay specifically";
    public function update_clopay()
    {
        $source = 'clopay';
        $this->log("update - $source");
        $source_config = $this->sources['clopay'];
        $url_template = $source_config['url'];

        $sourceObj = new PXBRO_Source($this, $source, $url_template, $this->cache);
        $data = new StdClass();
        $data->products_by_type = array();

        foreach (['Residential','Entrydoor','Commercial'] as $type)
        {
            $url = str_replace('{type}', $type, $url_template);
            $this->output("Fetching product list from $url");
            $typeSource = new PXBRO_Source($this, $source, $url, $this->cache);
            $typeSource->readJSON();
            $products = $typeSource->json;
            if (!is_array($products))
            {
                $products = [];
            }
            $data->products_by_type[$type] = $products;
        }

        $sourceObj->json = $data;
        return $sourceObj->saveHTML('index');
    }

    protected $___sync = "Sync source files based on configured repository";
    public function sync()
    {
        if (empty($this->sync)) return;

        $this->output('Syncing...');

        if (substr($this->sync, 0, 4) == 'git@')
        {
            // Temporarily switch to config_dir
            $original_dir = getcwd();
            $output_dir = __DIR__ . DS . '..' . DS . 'output';
            $this->log($output_dir);
            chdir($output_dir);

            // Set up git if not already done
            if (!is_dir($output_dir . DS . '.git'))
            {
                $this->log('Running commands to initialize git');
                $this->exec("git init");
                $this->exec("git remote add sync {$this->sync}");
            }

            // Push
            $this->log('Committing and pushing to remote (sync)');
            $this->exec("git add . --all");
            $this->exec("git commit -m \"Automatic sync commit - {$this->stamp()}\"");
            $this->exec("git push sync master");

            // Switch back to original directory
            chdir($original_dir);
        }
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
            "cat" => [
                "url" => "http://h-cpc.cat.com/cmms/v2?&lid=en&sc=US"
            ],
            "hyster" => [
                "url" => "http://www.hyster.com/NaccoAdmin/moonda.xml"
            ],
            "menu" => [
                "url" => "https://www.w3schools.com/xml/simple.xml"
            ]
        ];

        parent::initConfig();
    }

    /**
     * Progress Bar Output
     */
    public function outputProgress($count, $total)
    {
        if (!$this->verbose)
        {
            if ($count > 0)
            {
                // Set cursor to first column
                echo chr(27) . "[0G";
                // Set cursor up 2 lines
                echo chr(27) . "[2A";
            }

            $pad = static::PAD_FULL - 1;
            $bar_count = floor(($count * $pad) / $total);
            $output = "[";
            $output = str_pad($output, $bar_count, "|");
            $output = str_pad($output, $pad, " ");
            $output.= "]";
            $this->output($output);
            $this->output(str_pad("$count/$total", static::PAD_FULL, " ", STR_PAD_LEFT));
        }
        else
        {
            $this->output("$count/$total");
        }
    }

}
PXBRO::run($argv);
?>
