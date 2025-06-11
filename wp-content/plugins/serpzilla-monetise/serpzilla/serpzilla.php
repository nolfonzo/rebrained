<?php
/**
 * Plugin for serpzilla.com webmaster services integration
 *
 * PHP-client
 *
 * Webmasters! You don't need to change anything in this file!
 * All settings - through the parameters when calling the code.
 *
 * Detailed information on adding a site to the system, installing the code, as well as on all other issues,
 * you can find here:
 * @link https://serpzilla.com/
 *
 */

/**
 * The main class that does all the routine
 */
class SERPZILLA_base
{
    protected $_version = '1.5.3 (SZ WP v1.0)';

    protected $_verbose = false;

    /**
     * Site encoding
     * @link http://www.php.net/manual/en/function.iconv.php
     * @var string
     */
    protected $_charset = '';

    protected $_serpzilla_charset = '';

    protected $_server_list = array('dispenser-01.saperu.net', 'dispenser-02.saperu.net');

    /**
     *
     * @var int
     */
    protected $_cache_lifetime = 3600;

    /**
     * If it was not possible to download the link base, then the next attempt will be in so many seconds
     * @var int
     */
    protected $_cache_reloadtime = 600;

    protected $_errors = array();

    protected $_host = '';

    protected $_request_uri = '';

    protected $_multi_site = true;

    /**
     * How to connect to a remote server [file_get_contents|curl|socket]
     * @var string
     */
    protected $_fetch_remote_type = '';

    /**
     * How long to wait for a response
     * @var int
     */
    protected $_socket_timeout = 6;

    protected $_force_show_code = false;

    /**
     * If our robot
     * @var bool
     */
    protected $_is_our_bot = false;

    protected $_debug                   = false;
    protected $_file_contents_for_debug = array();

    /**
     * Case-insensitive mode of operation, use only at your own risk
     * @var bool
     */
    protected $_ignore_case = false;

    /**
     * Path to data
     * @var string
     */
    protected $_db_file = '';

    /**
     * Request Format. serialize|php-require
     * @var string
     */
    protected $_format = 'serialize';

    /**
     * Flag for splitting links.db into separate files.
     * @var bool
     */
    protected $_split_data_file = true;
    /**
     * Where will we get the page uri from: $_SERVER['REQUEST_URI'] or getenv('REQUEST_URI')
     * @var bool
     */
    protected $_use_server_array = false;

    /**
     * Whether to show js code separately from rendered content
     *
     * @var bool
     */
    protected $_show_counter_separately = false;

    protected $_force_update_db = false;

    protected $_user_agent = '';

    public function __construct($options = null)
    {
        $host = '';

        if (is_array($options)) {
            if (isset($options['host'])) {
                $host = $options['host'];
            }
        } elseif (strlen($options)) {
            $host    = $options;
            $options = array();
        } else {
            $options = array();
        }

        if (isset($options['use_server_array']) && $options['use_server_array'] == true) {
            $this->_use_server_array = true;
        }

        // Which site?
        if (strlen($host)) {
            $this->_host = $host;
        } else {
            $this->_host = $_SERVER['HTTP_HOST'];
        }

        $this->_host = mb_strtolower($this->_host, 'UTF-8');
        $this->_host = preg_replace('/^http:\/\//', '', $this->_host);
        $this->_host = preg_replace('/^www\./', '', $this->_host);

        // Which page?
        if (isset($options['request_uri']) && strlen($options['request_uri'])) {
            $this->_request_uri = $options['request_uri'];
        } elseif ($this->_use_server_array === false) {
            $this->_request_uri = getenv('REQUEST_URI');
        }

        if (strlen($this->_request_uri) == 0) {
            $this->_request_uri = $_SERVER['REQUEST_URI'];
        }

        // In case you want many sites in one folder
        if (isset($options['multi_site']) && $options['multi_site'] == true) {
            $this->_multi_site = true;
        }

        // Display information about debugging
        if (isset($options['debug']) && $options['debug'] == true) {
            $this->_debug = true;
        }

        // Are we defining our robot?
        if (isset($_COOKIE['sape_cookie']) && ($_COOKIE['sape_cookie'] == _SERPZILLA_USER)) {
            $this->_is_our_bot = true;
            if (isset($_COOKIE['sape_debug']) && ($_COOKIE['sape_debug'] == 1)) {
                $this->_debug = true;

                $this->_options            = $options;
                $this->_server_request_uri = $_SERVER['REQUEST_URI'];
                $this->_getenv_request_uri = getenv('REQUEST_URI');
                $this->_SERPZILLA_USER          = _SERPZILLA_USER;
            }
            if (isset($_COOKIE['sape_updatedb']) && ($_COOKIE['sape_updatedb'] == 1)) {
                $this->_force_update_db = true;
            }
        } else {
            $this->_is_our_bot = false;
        }


        if (isset($options['verbose']) && $options['verbose'] == true || $this->_debug) {
            $this->_verbose = true;
        }

        if (isset($options['charset']) && strlen($options['charset'])) {
            $this->_charset = $options['charset'];
        } else {
            $this->_charset = 'windows-1251';
        }

        if (isset($options['fetch_remote_type']) && strlen($options['fetch_remote_type'])) {
            $this->_fetch_remote_type = $options['fetch_remote_type'];
        }

        if (isset($options['socket_timeout']) && is_numeric($options['socket_timeout']) && $options['socket_timeout'] > 0) {
            $this->_socket_timeout = $options['socket_timeout'];
        }

        if (isset($options['force_show_code']) && $options['force_show_code'] == true) {
            $this->_force_show_code = true;
        }

        if (!defined('_SERPZILLA_USER')) {
            return $this->_raise_error('_SERPZILLA_USER constant not set');
        }

        if (isset($options['ignore_case']) && $options['ignore_case'] == true) {
            $this->_ignore_case = true;
            $this->_request_uri = strtolower($this->_request_uri);
        }

        if (isset($options['show_counter_separately'])) {
            $this->_show_counter_separately = (bool)$options['show_counter_separately'];
        }

        if (isset($options['format']) && in_array($options['format'], array('serialize', 'php-require'))) {
            $this->_format = $options['format'];
        }

        if (isset($options['split_data_file'])) {
            $this->_split_data_file = (bool)$options['split_data_file'];
        }
    }

    /**
     * Get string User-Agent
     *
     * @return string
     */
    protected function _get_full_user_agent_string()
    {
        return $this->_user_agent . ' ' . $this->_version;
    }

    /**
     * Output debug information
     *
     * @param $data
     *
     * @return string
     */
    protected function _debug_output($data)
    {
        $data = '<!-- <sape_debug_info>' . @base64_encode(serialize($data)) . '</sape_debug_info> -->';

        return $data;
    }

    /**
     * Function for connecting to a remote server
     */
    protected function _fetch_remote_file($host, $path, $specifyCharset = false)
    {

        $user_agent = $this->_get_full_user_agent_string();

        @ini_set('allow_url_fopen', 1);
        @ini_set('default_socket_timeout', $this->_socket_timeout);
        @ini_set('user_agent', $user_agent);
        if (
            $this->_fetch_remote_type == 'file_get_contents'
            ||
            (
                $this->_fetch_remote_type == ''
                &&
                function_exists('file_get_contents')
                &&
                ini_get('allow_url_fopen') == 1
            )
        ) {
            $this->_fetch_remote_type = 'file_get_contents';

            if ($specifyCharset && function_exists('stream_context_create')) {
                $opts    = array(
                    'http' => array(
                        'method' => 'GET',
                        'header' => 'Accept-Charset: ' . $this->_charset . "\r\n"
                    )
                );
                $context = @stream_context_create($opts);
                if ($data = @file_get_contents('http://' . $host . $path, null, $context)) {
                    return $data;
                }
            } else {
                if ($data = @file_get_contents('http://' . $host . $path)) {
                    return $data;
                }
            }
        } elseif (
            $this->_fetch_remote_type == 'curl'
            ||
            (
                $this->_fetch_remote_type == ''
                &&
                function_exists('curl_init')
            )
        ) {
            $this->_fetch_remote_type = 'curl';
            if ($ch = @curl_init()) {

                @curl_setopt($ch, CURLOPT_URL, 'http://' . $host . $path);
                @curl_setopt($ch, CURLOPT_HEADER, false);
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_socket_timeout);
                @curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
                if ($specifyCharset) {
                    @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Charset: ' . $this->_charset));
                }

                $data = @curl_exec($ch);
                @curl_close($ch);

                if ($data) {
                    return $data;
                }
            }
        } else {
            $this->_fetch_remote_type = 'socket';
            $buff                     = '';
            $fp                       = @fsockopen($host, 80, $errno, $errstr, $this->_socket_timeout);
            if ($fp) {
                @fputs($fp, "GET {$path} HTTP/1.0\r\nHost: {$host}\r\n");
                if ($specifyCharset) {
                    @fputs($fp, "Accept-Charset: {$this->_charset}\r\n");
                }
                @fputs($fp, "User-Agent: {$user_agent}\r\n\r\n");
                while (!@feof($fp)) {
                    $buff .= @fgets($fp, 128);
                }
                @fclose($fp);

                $page = explode("\r\n\r\n", $buff);
                unset($page[0]);

                return implode("\r\n\r\n", $page);
            }
        }

        return $this->_raise_error('Unable to connect to server:' . $host . $path . ', type: ' . $this->_fetch_remote_type);
    }

    /**
     * Read function from local file
     */
    protected function _read($filename)
    {

        $fp = @fopen($filename, 'rb');
        @flock($fp, LOCK_SH);
        if ($fp) {
            clearstatcache();
            $length = @filesize($filename);

            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                $mqr = @get_magic_quotes_runtime();
                @set_magic_quotes_runtime(0);
            }

            if ($length) {
                $data = @fread($fp, $length);
            } else {
                $data = '';
            }

            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                @set_magic_quotes_runtime($mqr);
            }

            @flock($fp, LOCK_UN);
            @fclose($fp);

            return $data;
        }

        return $this->_raise_error('Unable to read data from the file: ' . $filename);
    }

    /**
     * Write function to local file
     */
    protected function _write($filename, $data)
    {

        $fp = @fopen($filename, 'ab');
        if ($fp) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                ftruncate($fp, 0);

                if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                    $mqr = @get_magic_quotes_runtime();
                    @set_magic_quotes_runtime(0);
                }

                @fwrite($fp, $data);

                if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                    @set_magic_quotes_runtime($mqr);
                }

                @flock($fp, LOCK_UN);
                @fclose($fp);

                if (md5($this->_read($filename)) != md5($data)) {
                    @unlink($filename);

                    return $this->_raise_error('Data integrity violated in process of the file recording: ' . $filename);
                }
            } else {
                return false;
            }

            return true;
        }

        return $this->_raise_error('Unable to record data into the file: ' . $filename);
    }

    /**
     * Error handling function
     */
    protected function _raise_error($e)
    {

        $this->_errors[] = $e;

        if ($this->_verbose == true) {
            print '<p style="color: red; font-weight: bold;">SERPZILLA ERROR: ' . $e . '</p>';
        }

        return false;
    }

    /**
     * Get data file name
     *
     * @return string
     */
    protected function _get_db_file()
    {
        return '';
    }

    /**
     * Get filename with meta information
     *
     * @return string
     */
    protected function _get_meta_file()
    {
        return '';
    }

    /**
     * Get file prefix in split_data_file mode.
     *
     * @return string
     */
    protected function _get_save_filename_prefix()
    {
        if ($this->_split_data_file) {
            return '.' . crc32($this->_request_uri) % 100;
        } else {
            return '';
        }
    }
    /**
     * Get the URI to the dispenser host
     *
     * @return string
     */
    protected function _get_dispenser_path()
    {
        return '';
    }

    /**
     * Store the data received from the file in an object
     */
    protected function _set_data($data)
    {
    }

    /**
     * Decrypts data
     *
     * @param string $data
     *
     * @return array|bool
     */
    protected function _uncode_data($data)
    {
        return @unserialize($data);
    }

    /**
     * Encrypts data for storage.
     *
     * @param $data
     *
     * @return string
     */
    protected function _code_data($data)
    {
        return @serialize($data);
    }

    /**
     * Saving data to a file.
     *
     * @param string $data
     * @param string $filename
     */
    protected function _save_data($data, $filename = '')
    {
        $this->_write($filename, $data);
    }
    /**
     * Loading data
     */
    protected function _load_data()
    {
        $this->_db_file = $this->_get_db_file();

        if (!is_file($this->_db_file)) {
            if (@touch($this->_db_file, time() - $this->_cache_lifetime - 1)) {
                @chmod($this->_db_file, 0666);
            } else {
                return $this->_raise_error('No file ' . $this->_db_file . '. Failed to create. Set permission 777 to the folder.');
            }
        }

        if (!is_writable($this->_db_file)) {
            return $this->_raise_error('No access to file record: ' . $this->_db_file . '! Set permission 777 to the folder.');
        }

        @clearstatcache();

        $data = $this->_read($this->_db_file);
        if (
            $this->_force_update_db
            || (
                !$this->_is_our_bot
                &&
                (
                    filemtime($this->_db_file) < (time() - $this->_cache_lifetime)
                )
            )
        ) {
            @touch($this->_db_file, (time() - $this->_cache_lifetime + $this->_cache_reloadtime));

            $path = $this->_get_dispenser_path();
            if (strlen($this->_charset)) {
                $path .= '&charset=' . $this->_charset;
            }
            if ($this->_format) {
                $path .= '&format=' . $this->_format;
            }
            foreach ($this->_server_list as $server) {
                if ($data = $this->_fetch_remote_file($server, $path)) {
                    if (substr($data, 0, 12) == 'FATAL ERROR:') {
                        $this->_raise_error($data);
                    } else {
                        $hash = $this->_uncode_data($data);
                        if ($hash != false) {
                            $hash['__sape_charset__']      = $this->_charset;
                            $hash['__last_update__']       = time();
                            $hash['__multi_site__']        = $this->_multi_site;
                            $hash['__fetch_remote_type__'] = $this->_fetch_remote_type;
                            $hash['__ignore_case__']       = $this->_ignore_case;
                            $hash['__php_version__']       = phpversion();
                            $hash['__server_software__']   = $_SERVER['SERVER_SOFTWARE'];

                            $data_new = $this->_code_data($hash);
                            if ($data_new) {
                                $data = $data_new;
                            }

                            $this->_save_data($data, $this->_db_file);
                            break;
                        }
                    }
                }
            }
        }

        if (strlen(session_id())) {
            $session            = session_name() . '=' . session_id();
            $this->_request_uri = str_replace(array('?' . $session, '&' . $session), '', $this->_request_uri);
        }
        $data = $this->_uncode_data($data);
        if ($this->_split_data_file) {
            $meta = $this->_uncode_data($this->_read($this->_get_meta_file()));
            if (!is_array($data)) {
                $data = array();
            }
            if (is_array($meta)) {
                $data = array_merge($data, $meta);
            }
        }
        $this->_set_data($data);

        return true;
    }

    protected function _return_obligatory_page_content()
    {
        $s_globals = new SERPZILLA_globals();

        $html = '';
        if (isset($this->_page_obligatory_output) && !empty($this->_page_obligatory_output)
            && false == $s_globals->page_obligatory_output_shown()
        ) {
            $s_globals->page_obligatory_output_shown(true);
            $html = $this->_page_obligatory_output;
        }

        return $html;
    }

    /**
     * Return js code
     * - only works when the constructor parameter show_counter_separately  = true
     *
     * @return string
     */
    public function return_counter()
    {
        if (false == $this->_show_counter_separately) {
            $this->_show_counter_separately = true;
        }

        return $this->_return_obligatory_page_content();
    }
}

/**
 * Global flags
 */
class SERPZILLA_globals
{

    protected function _get_toggle_flag($name, $toggle = false)
    {

        static $flags = array();

        if (!isset($flags[$name])) {
            $flags[$name] = false;
        }

        if ($toggle) {
            $flags[$name] = true;
        }

        return $flags[$name];
    }

    public function block_css_shown($toggle = false)
    {
        return $this->_get_toggle_flag('block_css_shown', $toggle);
    }

    public function block_ins_beforeall_shown($toggle = false)
    {
        return $this->_get_toggle_flag('block_ins_beforeall_shown', $toggle);
    }

    public function page_obligatory_output_shown($toggle = false)
    {
        return $this->_get_toggle_flag('page_obligatory_output_shown', $toggle);
    }
}

/**
 * Class for working with regular links
 */
class SERPZILLA_client extends SERPZILLA_base
{

    protected $_links_delimiter = '';
    protected $_links           = array();
    protected $_links_page      = array();
    protected $_teasers_page    = array();

    protected $_user_agent         = 'SerpZilla_Client PHP';
    protected $_show_only_block    = false;
    protected $_block_tpl          = '';
    protected $_block_tpl_options  = array();
    protected $_block_uri_idna     = array();
    protected $_return_links_calls;
    protected $_teasers_css_showed = false;

    /**
     * @var SERPZILLA_rtb
     */
    protected $_teasers_rtb_proxy  = null;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (isset($options['rtb']) && !empty($options['rtb']) && $options['rtb'] instanceof SERPZILLA_rtb) {
            $this->_teasers_rtb_proxy = $options['rtb'];
        }

        $this->_load_data();
    }

    /**
     * Handling html for an array of links
     *
     * @param string     $html
     * @param null|array $options
     *
     * @return string
     */
    protected function _return_array_links_html($html, $options = null)
    {

        if (empty($options)) {
            $options = array();
        }

        if (
            strlen($this->_charset) > 0
            &&
            strlen($this->_serpzilla_charset) > 0
            &&
            $this->_serpzilla_charset != $this->_charset
            &&
            function_exists('iconv')
        ) {
            $new_html = @iconv($this->_serpzilla_charset, $this->_charset, $html);
            if ($new_html) {
                $html = $new_html;
            }
        }

        if ($this->_is_our_bot) {

            $html = '<sape_noindex>' . $html . '</sape_noindex>';

            if (isset($options['is_block_links']) && true == $options['is_block_links']) {

                if (!isset($options['nof_links_requested'])) {
                    $options['nof_links_requested'] = 0;
                }
                if (!isset($options['nof_links_displayed'])) {
                    $options['nof_links_displayed'] = 0;
                }
                if (!isset($options['nof_obligatory'])) {
                    $options['nof_obligatory'] = 0;
                }
                if (!isset($options['nof_conditional'])) {
                    $options['nof_conditional'] = 0;
                }

                $html = '<sape_block nof_req="' . $options['nof_links_requested'] .
                    '" nof_displ="' . $options['nof_links_displayed'] .
                    '" nof_oblig="' . $options['nof_obligatory'] .
                    '" nof_cond="' . $options['nof_conditional'] .
                    '">' . $html .
                    '</sape_block>';
            }
        }

        return $html;
    }

    /**
     * Final processing of html before displaying links
     *
     * @param string $html
     *
     * @return string
     */
    protected function _return_html($html)
    {
        if (false == $this->_show_counter_separately) {
            $html = $this->_return_obligatory_page_content() . $html;
        }

        return $this->_add_debug_info($html);
    }

    protected function _add_debug_info($html)
    {
        if ($this->_debug) {
            if (!empty($this->_links['__sape_teaser_images_path__'])) {
                $this->_add_file_content_for_debug($this->_links['__sape_teaser_images_path__']);
            }
            $this->_add_file_content_for_debug('.htaccess');

            $html .= $this->_debug_output($this);
        }

        return $html;
    }

    protected function _add_file_content_for_debug($file_name)
    {
        $path                                               = realpath(
            rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . strtok($file_name, '?')
        );
        $this->_file_contents_for_debug[$file_name]['path'] = $path;
        if ($path) {
            $this->_file_contents_for_debug[$file_name]['contents'] = @file_get_contents($path);
        }
    }

    /**
     *If a specific encoding is requested, and the cache encoding is known, and they are different, convert to the given one
     */
    protected function _convertCharset($html)
    {
        if (strlen($this->_charset) > 0
            && strlen($this->_serpzilla_charset) > 0
            && $this->_serpzilla_charset != $this->_charset
            && function_exists('iconv')
        ) {
            $new_html = @iconv($this->_serpzilla_charset, $this->_charset, $html);
            if ($new_html) {
                $html = $new_html;
            }
        }

        return $html;
    }

    /**
     * Output links as a block
     *
     * - Note: since version 1.2.2 the second $offset argument has been removed. If you send it according to the old
     * signature, then it will be ignored.
     *
     * @param int   $n       The number of links to display in the current block
     * @param array $options Options
     *
     * <code>
     * $options = array();
     * $options['block_no_css'] = (false|true);
     * // Overrides the css output ban in the page code: false - output css
     * $options['block_orientation'] = (1|0);
     * // Redefines the block orientation: 1 - horizontal, 0 - vertical
     * $options['block_width'] = ('auto'|'[?]px'|'[?]%'|'[?]');
     * // Redefines the block width:
     * // 'auto'  - determined by the width of the fixed-width ancestor block,
     * // if there is none, it will take the entire width
     * // '[?]px' - pixel value
     * // '[?]%'  - value as a percentage of the width of the fixed-width ancestor block
     * // '[?]'   - any other value that is supported by the CSS specification
     * </code>
     *
     * @see return_links()
     * @see return_counter()
     *
     * @return string
     */
    public function return_block_links($n = null, $options = null)
    {

        $numargs = func_num_args();
        $args    = func_get_args();

        if (2 == $numargs) {           // return_links($n, $options)
            if (!is_array($args[1])) { // return_links($n, $offset) - deprecated!
                $options = null;
            }
        } elseif (2 < $numargs) { // return_links($n, $offset, $options) - deprecated!

            if (!is_array($options)) {
                $options = $args[2];
            }
        }

        if (empty($options)) {
            $options = array();
        }

        $defaults                      = array();
        $defaults['block_no_css']      = false;
        $defaults['block_orientation'] = 1;
        $defaults['block_width']       = '';

        $ext_options = array();
        if (isset($this->_block_tpl_options) && is_array($this->_block_tpl_options)) {
            $ext_options = $this->_block_tpl_options;
        }

        $options = array_merge($defaults, $ext_options, $options);

        if (!is_array($this->_links_page)) {
            $html = $this->_return_array_links_html('', array('is_block_links' => true));

            return $this->_return_html($this->_links_page . $html);
        }
        elseif (!isset($this->_block_tpl)) {
            return $this->_return_html('');
        }


        $total_page_links = count($this->_links_page);

        $need_show_obligatory_block  = false;
        $need_show_conditional_block = false;
        $n_requested                 = 0;

        if (isset($this->_block_ins_itemobligatory)) {
            $need_show_obligatory_block = true;
        }

        if (is_numeric($n) && $n >= $total_page_links) {

            $n_requested = $n;

            if (isset($this->_block_ins_itemconditional)) {
                $need_show_conditional_block = true;
            }
        }

        if (!is_numeric($n) || $n > $total_page_links) {
            $n = $total_page_links;
        }

        $links = array();
        for ($i = 1; $i <= $n; $i++) {
            $links[] = array_shift($this->_links_page);
        }

        $html = '';

        $nof_conditional = 0;
        if (count($links) < $n_requested && true == $need_show_conditional_block) {
            $nof_conditional = $n_requested - count($links);
        }

        if (empty($links) && $need_show_obligatory_block == false && $nof_conditional == 0) {

            $return_links_options = array(
                'is_block_links'      => true,
                'nof_links_requested' => $n_requested,
                'nof_links_displayed' => 0,
                'nof_obligatory'      => 0,
                'nof_conditional'     => 0
            );

            $html = $this->_return_array_links_html($html, $return_links_options);

            return $this->_return_html($html);
        }

        $s_globals = new SERPZILLA_globals();
        if (!$s_globals->block_css_shown() && false == $options['block_no_css']) {
            $html .= $this->_block_tpl['css'];
            $s_globals->block_css_shown(true);
        }

        if (isset($this->_block_ins_beforeall) && !$s_globals->block_ins_beforeall_shown()) {
            $html .= $this->_block_ins_beforeall;
            $s_globals->block_ins_beforeall_shown(true);
        }
        unset($s_globals);

        if (isset($this->_block_ins_beforeblock)) {
            $html .= $this->_block_ins_beforeblock;
        }

        $block_tpl_parts = $this->_block_tpl[$options['block_orientation']];

        $block_tpl          = $block_tpl_parts['block'];
        $item_tpl           = $block_tpl_parts['item'];
        $item_container_tpl = $block_tpl_parts['item_container'];
        $item_tpl_full      = str_replace('{item}', $item_tpl, $item_container_tpl);
        $items              = '';

        $nof_items_total = count($links);
        foreach ($links as $link) {

            $is_found = preg_match('#<a href="(https?://([^"/]+)[^"]*)"[^>]*>[\s]*([^<]+)</a>#i', $link, $link_item);
            if (!$is_found) {
                preg_match('#<a href="(https?://([^"/]+)[^"]*)"[^>]*><img.*?alt="(.*?)".*?></a>#i', $link, $link_item);
            }

            if (function_exists('mb_strtoupper') && strlen($this->_serpzilla_charset) > 0) {
                $header_rest         = mb_substr($link_item[3], 1, mb_strlen($link_item[3], $this->_serpzilla_charset) - 1, $this->_serpzilla_charset);
                $header_first_letter = mb_strtoupper(mb_substr($link_item[3], 0, 1, $this->_serpzilla_charset), $this->_serpzilla_charset);
                $link_item[3]        = $header_first_letter . $header_rest;
            } elseif (function_exists('ucfirst') && (strlen($this->_serpzilla_charset) == 0 || strpos($this->_serpzilla_charset, '1251') !== false)) {
                $link_item[3][0] = ucfirst($link_item[3][0]);
            }

            if (isset($this->_block_uri_idna) && isset($this->_block_uri_idna[$link_item[2]])) {
                $link_item[2] = $this->_block_uri_idna[$link_item[2]];
            }

            $item = $item_tpl_full;
            $item = str_replace('{header}', $link_item[3], $item);
            $item = str_replace('{text}', trim($link), $item);
            $item = str_replace('{url}', $link_item[2], $item);
            $item = str_replace('{link}', $link_item[1], $item);
            $items .= $item;
        }

        if (true == $need_show_obligatory_block) {
            $items .= str_replace('{item}', $this->_block_ins_itemobligatory, $item_container_tpl);
            $nof_items_total += 1;
        }

        if ($need_show_conditional_block == true && $nof_conditional > 0) {
            for ($i = 0; $i < $nof_conditional; $i++) {
                $items .= str_replace('{item}', $this->_block_ins_itemconditional, $item_container_tpl);
            }
            $nof_items_total += $nof_conditional;
        }

        if ($items != '') {
            $html .= str_replace('{items}', $items, $block_tpl);

            if ($nof_items_total > 0) {
                $html = str_replace('{td_width}', round(100 / $nof_items_total), $html);
            } else {
                $html = str_replace('{td_width}', 0, $html);
            }

            if (isset($options['block_width']) && !empty($options['block_width'])) {
                $html = str_replace('{block_style_custom}', 'style="width: ' . $options['block_width'] . '!important;"', $html);
            }
        }

        unset($block_tpl_parts, $block_tpl, $items, $item, $item_tpl, $item_container_tpl);

        if (isset($this->_block_ins_afterblock)) {
            $html .= $this->_block_ins_afterblock;
        }

        unset($options['block_no_css'], $options['block_orientation'], $options['block_width']);

        $tpl_modifiers = array_keys($options);
        foreach ($tpl_modifiers as $k => $m) {
            $tpl_modifiers[$k] = '{' . $m . '}';
        }
        unset($m, $k);

        $tpl_modifiers_values = array_values($options);

        $html = str_replace($tpl_modifiers, $tpl_modifiers_values, $html);
        unset($tpl_modifiers, $tpl_modifiers_values);

        $clear_modifiers_regexp = '#\{[a-z\d_\-]+\}#';
        $html                   = preg_replace($clear_modifiers_regexp, ' ', $html);

        $return_links_options = array(
            'is_block_links'      => true,
            'nof_links_requested' => $n_requested,
            'nof_links_displayed' => $n,
            'nof_obligatory'      => ($need_show_obligatory_block == true ? 1 : 0),
            'nof_conditional'     => $nof_conditional
        );

        $html = $this->_return_array_links_html($html, $return_links_options);

        return $this->_return_html($html);
    }

    /**
     * Displaying links in the usual form - text with a separator
     *
     * - Note: since version 1.2.2 the second $offset argument has been removed. If you send it according to the
     * old signature, then it will be ignored.
     *
     * @param int   $n       Number of links to display
     * @param array $options Options
     *
     * <code>
     * $options = array();
     * $options['as_block'] = (false|true);
     * // Whether to show links as a block
     * </code>
     *
     * @see return_block_links()
     * @see return_counter()
     *
     * @return string
     */
    public function return_links($n = null, $options = null)
    {

        if ($this->_debug) {
            if (function_exists('debug_backtrace')) {
                $this->_return_links_calls[] = debug_backtrace();
            } else {
                $this->_return_links_calls = "(function_exists('debug_backtrace')==false";
            }
        }

        $numargs = func_num_args();
        $args    = func_get_args();

        if (2 == $numargs) {           // return_links($n, $options)
            if (!is_array($args[1])) { // return_links($n, $offset) - deprecated!
                $options = null;
            }
        } elseif (2 < $numargs) {        // return_links($n, $offset, $options) - deprecated!

            if (!is_array($options)) {
                $options = $args[2];
            }
        }

        $as_block = $this->_show_only_block;

        if (is_array($options) && isset($options['as_block']) && false == $as_block) {
            $as_block = $options['as_block'];
        }

        if (true == $as_block && isset($this->_block_tpl)) {
            return $this->return_block_links($n, $options);
        }

        if (is_array($this->_links_page)) {

            $total_page_links = count($this->_links_page);

            if (!is_numeric($n) || $n > $total_page_links) {
                $n = $total_page_links;
            }

            $links = array();

            for ($i = 1; $i <= $n; $i++) {
                $links[] = array_shift($this->_links_page);
            }

            $html = $this->_convertCharset(join($this->_links_delimiter, $links));

            if ($this->_is_our_bot) {
                $html = '<sape_noindex>' . $html . '</sape_noindex>';
            }
        } else {
            $html = $this->_links_page;
            if ($this->_is_our_bot) {
                $html .= '<sape_noindex></sape_noindex>';
            }
        }

        $html = $this->_return_html($html);

        return $html;
    }

    protected function _get_db_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.links' . $this->_get_save_filename_prefix() . '.db';
        } else {
            return dirname(__FILE__) . '/links' . $this->_get_save_filename_prefix() . '.db';
        }
    }

    protected function _get_meta_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.links.meta.db';
        } else {
            return dirname(__FILE__) . '/links.meta.db';
        }
    }

    protected function _get_dispenser_path()
    {
        return '/code.php?user=' . _SERPZILLA_USER . '&host=' . $this->_host;
    }

    protected function _set_data($data)
    {
        if ($this->_ignore_case) {
            $this->_links = array_change_key_case($data);
        } else {
            $this->_links = $data;
        }
        if (isset($this->_links['__sape_delimiter__'])) {
            $this->_links_delimiter = $this->_links['__sape_delimiter__'];
        }

        if (isset($this->_links['__sape_charset__'])) {
            $this->_serpzilla_charset = $this->_links['__sape_charset__'];
        } else {
            $this->_serpzilla_charset = '';
        }
        if (isset($this->_links) && is_array($this->_links)
            && @array_key_exists($this->_request_uri, $this->_links) && is_array($this->_links[$this->_request_uri])) {
            $this->_links_page = $this->_links[$this->_request_uri];
        } else {
            if (isset($this->_links['__sape_new_url__']) && strlen($this->_links['__sape_new_url__'])) {
                if ($this->_is_our_bot || $this->_force_show_code) {
                    $this->_links_page = $this->_links['__sape_new_url__'];
                }
            }
        }

        if (isset($this->_links['__sape_teasers__']) && is_array($this->_links['__sape_teasers__'])
            && @array_key_exists($this->_request_uri, $this->_links['__sape_teasers__']) && is_array($this->_links['__sape_teasers__'][$this->_request_uri])) {
            $this->_teasers_page = $this->_links['__sape_teasers__'][$this->_request_uri];
        }

        if (isset($this->_links['__sape_page_obligatory_output__'])) {
            if ($this->_teasers_rtb_proxy !== null) {
                $this->_page_obligatory_output = $this->_teasers_rtb_proxy->return_script();
            } else {
                $this->_page_obligatory_output = $this->_links['__sape_page_obligatory_output__'];
            }
        }

        if (isset($this->_links['__sape_show_only_block__'])) {
            $this->_show_only_block = $this->_links['__sape_show_only_block__'];
        } else {
            $this->_show_only_block = false;
        }

        if (isset($this->_links['__sape_block_tpl__']) && !empty($this->_links['__sape_block_tpl__'])
            && is_array($this->_links['__sape_block_tpl__'])
        ) {
            $this->_block_tpl = $this->_links['__sape_block_tpl__'];
        }

        if (isset($this->_links['__sape_block_tpl_options__']) && !empty($this->_links['__sape_block_tpl_options__'])
            && is_array($this->_links['__sape_block_tpl_options__'])
        ) {
            $this->_block_tpl_options = $this->_links['__sape_block_tpl_options__'];
        }

        // IDNA-domens
        if (isset($this->_links['__sape_block_uri_idna__']) && !empty($this->_links['__sape_block_uri_idna__'])
            && is_array($this->_links['__sape_block_uri_idna__'])
        ) {
            $this->_block_uri_idna = $this->_links['__sape_block_uri_idna__'];
        }

        $check_blocks = array(
            'beforeall',
            'beforeblock',
            'afterblock',
            'itemobligatory',
            'itemconditional',
            'afterall'
        );

        foreach ($check_blocks as $block_name) {

            $var_name  = '__sape_block_ins_' . $block_name . '__';
            $prop_name = '_block_ins_' . $block_name;

            if (isset($this->_links[$var_name]) && strlen($this->_links[$var_name]) > 0) {
                $this->$prop_name = $this->_links[$var_name];
            }
        }
    }

    protected function _uncode_data($data)
    {
        if ($this->_format == 'php-require') {
            $data1 = str_replace('<?php return ', '', $data);
            eval('$data = ' . $data1 . ';');
            return $data;
        }

        return @unserialize($data);
    }

    protected function _code_data($data)
    {
        if ($this->_format == 'php-require') {
            return var_export($data, true);
        }

        return @serialize($data);
    }

    protected function _save_data($data, $filename = '')
    {
        if ($this->_split_data_file) {
            $directory = dirname(__FILE__) . '/';
            $hashArray = array();
            $data = $this->_uncode_data($data);
            foreach ($data as $url => $item) {
                if (preg_match('/\_\_.+\_\_/mu', $url)) {
                    $currentFile = 'links.meta.db';
                } else {
                    $currentFile = 'links.' . crc32($url) % 100 . '.db';
                }
                if ($this->_multi_site) {
                    $currentFile = $this->_host . '.' . $currentFile;
                }
                $hashArray[$currentFile][$url] = $item;
            }
            foreach ($hashArray as $file => $array) {
                $this->_write($directory . $file, $this->_code_data($array));
            }
            if (!isset($hashArray[basename($filename)])) {
                parent::_save_data('', $filename);
            }
        } else {
            parent::_save_data($data, $filename);
        }
    }
}

/**
 * Class for working with contextual links
 */
class SERPZILLA_context extends SERPZILLA_base
{

    protected $_words       = array();
    protected $_words_page  = array();
    protected $_user_agent  = 'SAPE_Context PHP';
    protected $_filter_tags = array('a', 'textarea', 'select', 'script', 'style', 'label', 'noscript', 'noindex', 'button');

    protected $_debug_actions = array();

    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->_load_data();
    }

    /**
     * Start collecting debug information
     */
    protected function _debug_action_start()
    {
        if (!$this->_debug) {
            return;
        }

        $this->_debug_actions   = array();
        $this->_debug_actions[] = $this->_get_full_user_agent_string();
    }

    /**
     * Write a line of debug information
     *
     * @param        $data
     * @param string $key
     */
    protected function _debug_action_append($data, $key = '')
    {
        if (!$this->_debug) {
            return;
        }

        if (!empty($key)) {
            $this->_debug_actions[] = array($key => $data);
        } else {
            $this->_debug_actions[] = $data;
        }
    }

    /**
     * Output of debug information
     *
     * @return string
     */
    protected function _debug_action_output()
    {

        if (!$this->_debug || empty($this->_debug_actions)) {
            return '';
        }

        $debug_info = $this->_debug_output($this->_debug_actions);

        $this->_debug_actions = array();

        return $debug_info;
    }

    /**
     * Replacing words in a piece of text and framing it with serpzilla_index tags
     */
    public function replace_in_text_segment($text)
    {

        $this->_debug_action_start();
        $this->_debug_action_append('START: replace_in_text_segment()');
        $this->_debug_action_append($text, 'argument for replace_in_text_segment');

        if (count($this->_words_page) > 0) {

            $source_sentences = array();

            foreach ($this->_words_page as $n => $sentence) {
                $special_chars = array(
                    '&amp;'  => '&',
                    '&quot;' => '"',
                    '&#039;' => '\'',
                    '&lt;'   => '<',
                    '&gt;'   => '>'
                );
                $sentence      = strip_tags($sentence);
                $sentence      = strip_tags($sentence);
                $sentence      = str_replace(array_keys($special_chars), array_values($special_chars), $sentence);

                $htsc_charset = empty($this->_charset) ? 'windows-1251' : $this->_charset;
                $quote_style  = ENT_COMPAT;
                if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                    $quote_style = ENT_COMPAT | ENT_HTML401;
                }

                $sentence = htmlspecialchars($sentence, $quote_style, $htsc_charset);

                $sentence      = preg_quote($sentence, '/');
                $replace_array = array();
                if (preg_match_all('/(&[#a-zA-Z0-9]{2,6};)/isU', $sentence, $out)) {
                    for ($i = 0; $i < count($out[1]); $i++) {
                        $unspec                 = $special_chars[$out[1][$i]];
                        $real                   = $out[1][$i];
                        $replace_array[$unspec] = $real;
                    }
                }
                foreach ($replace_array as $unspec => $real) {
                    $sentence = str_replace($real, '((' . $real . ')|(' . $unspec . '))', $sentence);
                }

                $source_sentences[$n] = str_replace(' ', '((\s)|(&nbsp;)|( ))+', $sentence);
            }

            $this->_debug_action_append($source_sentences, 'sentences for replace');

            $first_part = true;

            if (count($source_sentences) > 0) {

                $content   = '';
                $open_tags = array();
                $close_tag = '';

                $part = strtok(' ' . $text, '<');

                while ($part !== false) {

                    if (preg_match('/(?si)^(\/?[a-z0-9]+)/', $part, $matches)) {
                        $tag_name = strtolower($matches[1]);

                        if (substr($tag_name, 0, 1) == '/') {
                            $close_tag = substr($tag_name, 1);
                            $this->_debug_action_append($close_tag, 'close tag');
                        } else {
                            $close_tag = '';
                            $this->_debug_action_append($tag_name, 'open tag');
                        }
                        $cnt_tags = count($open_tags);

                        if (($cnt_tags > 0) && ($open_tags[$cnt_tags - 1] == $close_tag)) {
                            array_pop($open_tags);

                            $this->_debug_action_append($tag_name, 'deleted from open_tags');

                            if ($cnt_tags - 1 == 0) {
                                $this->_debug_action_append('start replacement');
                            }
                        }


                        if (count($open_tags) == 0) {

                            if (!in_array($tag_name, $this->_filter_tags)) {
                                $split_parts = explode('>', $part, 2);

                                if (count($split_parts) == 2) {

                                    foreach ($source_sentences as $n => $sentence) {
                                        if (preg_match('/' . $sentence . '/', $split_parts[1]) == 1) {
                                            $split_parts[1] = preg_replace('/' . $sentence . '/', str_replace('$', '\$', $this->_words_page[$n]), $split_parts[1], 1);

                                            $this->_debug_action_append($sentence . ' --- ' . $this->_words_page[$n], 'replaced');

                                            unset($source_sentences[$n]);
                                            unset($this->_words_page[$n]);
                                        }
                                    }
                                    $part = $split_parts[0] . '>' . $split_parts[1];
                                    unset($split_parts);
                                }
                            } else {
                                $open_tags[] = $tag_name;

                                $this->_debug_action_append($tag_name, 'added to open_tags, stop replacement');
                            }
                        }
                    } elseif (count($open_tags) == 0) {

                        foreach ($source_sentences as $n => $sentence) {
                            if (preg_match('/' . $sentence . '/', $part) == 1) {
                                $part = preg_replace('/' . $sentence . '/', str_replace('$', '\$', $this->_words_page[$n]), $part, 1);

                                $this->_debug_action_append($sentence . ' --- ' . $this->_words_page[$n], 'replaced');

                                unset($source_sentences[$n]);
                                unset($this->_words_page[$n]);
                            }
                        }
                    }

                    if ($first_part) {
                        $content .= $part;
                        $first_part = false;
                    } else {
                        $content .= '<' . $part;
                    }

                    unset($part);
                    $part = strtok('<');
                }
                $text = ltrim($content);
                unset($content);
            }
        } else {
            $this->_debug_action_append('No word\'s for page');
        }

        if ($this->_is_our_bot || $this->_force_show_code || $this->_debug) {
            $text = '<sape_index>' . $text . '</sape_index>';
            if (isset($this->_words['__sape_new_url__']) && strlen($this->_words['__sape_new_url__'])) {
                $text .= $this->_words['__sape_new_url__'];
            }
        }

        if (count($this->_words_page) > 0) {
            $this->_debug_action_append($this->_words_page, 'Not replaced');
        }

        $this->_debug_action_append('END: replace_in_text_segment()');

        $text .= $this->_debug_action_output();

        return $text;
    }

    /**
     * Word replacement
     */
    public function replace_in_page($buffer)
    {

        $this->_debug_action_start();
        $this->_debug_action_append('START: replace_in_page()');

        $s_globals = new SERPZILLA_globals();

        if (!$s_globals->page_obligatory_output_shown()
            && isset($this->_page_obligatory_output)
            && !empty($this->_page_obligatory_output)
        ) {

            $split_content = preg_split('/(?smi)(<\/?body[^>]*>)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (count($split_content) == 5) {
                $buffer = $split_content[0] . $split_content[1] . $split_content[2]
                    . (false == $this->_show_counter_separately ? $this->_return_obligatory_page_content() : '')
                    . $split_content[3] . $split_content[4];
                unset($split_content);

                $s_globals->page_obligatory_output_shown(true);
            }
        }

        if (count($this->_words_page) > 0) {
            $split_content = preg_split('/(?smi)(<\/?sape_index>)/', $buffer, -1);
            $cnt_parts     = count($split_content);
            if ($cnt_parts > 1) {
                if ($cnt_parts >= 3) {
                    for ($i = 1; $i < $cnt_parts; $i = $i + 2) {
                        $split_content[$i] = $this->replace_in_text_segment($split_content[$i]);
                    }
                }
                $buffer = implode('', $split_content);

                $this->_debug_action_append($cnt_parts, 'Split by Sape_index cnt_parts=');
            } else {
                $split_content = preg_split('/(?smi)(<\/?body[^>]*>)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (count($split_content) == 5) {
                    $split_content[0] = $split_content[0] . $split_content[1];
                    $split_content[1] = $this->replace_in_text_segment($split_content[2]);
                    $split_content[2] = $split_content[3] . $split_content[4];
                    unset($split_content[3]);
                    unset($split_content[4]);
                    $buffer = $split_content[0] . $split_content[1] . $split_content[2];

                    $this->_debug_action_append('Split by BODY');
                } else {
                    $this->_debug_action_append('Cannot split by BODY');
                }
            }
        } else {
            if (!$this->_is_our_bot && !$this->_force_show_code && !$this->_debug) {
                $buffer = preg_replace('/(?smi)(<\/?sape_index>)/', '', $buffer);
            } else {
                if (isset($this->_words['__sape_new_url__']) && strlen($this->_words['__sape_new_url__'])) {
                    $buffer .= $this->_words['__sape_new_url__'];
                }
            }

            $this->_debug_action_append('No word\'s for page');
        }

        $this->_debug_action_append('STOP: replace_in_page()');
        $buffer .= $this->_debug_action_output();

        return $buffer;
    }

    protected function _get_db_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.words' . $this->_get_save_filename_prefix() . '.db';
        } else {
            return dirname(__FILE__) . '/words' . $this->_get_save_filename_prefix() . '.db';
        }
    }

    protected function _get_meta_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.words.meta.db';
        } else {
            return dirname(__FILE__) . '/words.meta.db';
        }
    }

    protected function _get_dispenser_path()
    {
        return '/code_context.php?user=' . _SERPZILLA_USER . '&host=' . $this->_host;
    }

    protected function _set_data($data)
    {
        $this->_words = $data;
        if (isset($this->_words) && is_array($this->_words)
            && @array_key_exists($this->_request_uri, $this->_words) && is_array($this->_words[$this->_request_uri])) {
            $this->_words_page = $this->_words[$this->_request_uri];
        }

        if (isset($this->_words['__sape_page_obligatory_output__'])) {
            $this->_page_obligatory_output = $this->_words['__sape_page_obligatory_output__'];
        }
    }

    protected function _uncode_data($data)
    {
        if ($this->_format == 'php-require') {
            $data1 = str_replace('<?php return ', '', $data);
            eval('$data = ' . $data1 . ';');
            return $data;
        }

        return @unserialize($data);
    }

    protected function _code_data($data)
    {
        if ($this->_format == 'php-require') {
            return var_export($data, true);
        }

        return @serialize($data);
    }

    protected function _save_data($data, $filename = '')
    {
        if ($this->_split_data_file) {
            $directory = dirname(__FILE__) . '/';
            $hashArray = array();
            $data = $this->_uncode_data($data);
            foreach ($data as $url => $item) {
                if (preg_match('/\_\_.+\_\_/mu', $url)) {
                    $currentFile = 'words.meta.db';
                } else {
                    $currentFile = 'words.' . crc32($url) % 100 . '.db';
                }
                if ($this->_multi_site) {
                    $currentFile = $this->_host . '.' . $currentFile;
                }
                $hashArray[$currentFile][$url] = $item;
            }
            foreach ($hashArray as $file => $array) {
                $this->_write($directory . $file, $this->_code_data($array));
            }
            if (!isset($hashArray[basename($filename)])) {
                parent::_save_data('', $filename);
            }
        } else {
            parent::_save_data($data, $filename);
        }
    }
}

/**
 * Class for working with client code rtb.serpzilla.com
 */
class SERPZILLA_rtb extends SERPZILLA_base
{

    protected $_site_id = null;

    protected $_ucode_id = null;

    protected $_ucode_url = null;

    protected $_ucode_filename = null;

    protected $_ucode_places = array();

    protected $_base_dir = null;

    protected $_base_url = '/';

    protected $_proxy_url = null;

    protected $_data = null;

    protected $_filename = null;

    protected $_server_list = array('rtb.sape.ru');

    protected $_format = false;

    protected $_split_data_file = false;

    protected $_return_script_shown = false;

    /**
     * SERPZILLA_rtb constructor.
     *
     * @param array $options
     */
    public function __construct($options = null)
    {
        if (isset($options['host'])) {
            $this->_host = $options['host'];
        } else {
            $this->_host = $_SERVER['HTTP_HOST'];
            $this->_host = preg_replace('/^http(?:s)?:\/\//', '', $this->_host);
            $this->_host = preg_replace('/^www\./', '', $this->_host);
        }

        if (isset($options['ucode_id'])) {
            $this->_ucode_id = $options['ucode_id'];
            if (isset($options['ucode_filename'])) {
                $this->_filename = preg_replace('~\.js$~', '', trim($options['ucode_filename'])) . '.js';
            } else {
                $this->_filename = $this->_ucode_id . '.js';
            }
            if (isset($options['filename'])) {
                $this->_ucode_filename = preg_replace('~\.js$~', '', trim($options['filename'])) . '.js';
            }
            if (isset($options['places'])) {
                $this->_ucode_places = $options['places'];
            }
        } elseif (isset($options['site_id'])) {
            $this->_site_id = $options['site_id'];
            if (isset($options['filename']) && $options['filename']) {
                $this->_filename = preg_replace('~\.js$~', '', trim($options['filename'])) . '.js';
            } else {
                $this->_filename = $this->_site_id . '.js';
            }
        }

        if ($this->_filename !== null) {
            if (isset($options['base_dir'])) {
                $this->_base_dir = preg_replace('~/$~', '', trim($options['base_dir'])) . '/';
            } else {
                $this->_base_dir = dirname(dirname(__FILE__)) . '/';
            }

            if (isset($options['base_url'])) {
                $this->_base_url = preg_replace('~/$~', '', trim($options['base_url'])) . '/';
            }

            if (isset($options['proxy_url'])) {
                $this->_proxy_url = strpos($options['proxy_url'], '?') === false ? ($options['proxy_url'] . '?') : (preg_replace('~&^~', '', '&' . $options['proxy_url'] . '&'));
            } else {
                $this->_proxy_url = '/proxy.php?';
            }

            $this->_load_data();
        } else {
            $this->_load_proxed_url();
        }
    }

    /**
     * Get data file name
     *
     * @return string
     */
    protected function _get_db_file()
    {
        if ($this->_ucode_id) {
            return dirname(__FILE__) . '/rtb.ucode.' . $this->_ucode_id . '.' . $this->_host . '.db';
        }

        return dirname(__FILE__) . '/rtb.site.' . $this->_site_id . '.' . $this->_host . '.db';
    }

    /**
     * Get the URI to the dispenser host
     *
     * @return string
     */
    protected function _get_dispenser_path()
    {
        if ($this->_ucode_id) {
            return '/dispenser/user/' . _SERPZILLA_USER . '/' . $this->_ucode_id;
        }

        return '/dispenser/site/' . _SERPZILLA_USER . '/' . $this->_site_id;
    }

    /**
     * @return bool
     */
    protected function _load_proxed_url()
    {
        $db_file = dirname(__FILE__) . '/rtb.proxy.db';
        if (!is_file($db_file)) {
            if (@touch($db_file)) {
                @chmod($db_file, 0666);
            } else {
                return $this->_raise_error('No file ' . $db_file . '. Failed to create. Set permission 777 to the folder.');
            }
        }
        if (!is_writable($db_file)) {
            return $this->_raise_error('No access to file record: ' . $db_file . '! Set permission 777 to the folder.');
        }

        @clearstatcache();

        $data = $this->_read($db_file);
        if ($data !== '') {
            $this->_data['__proxy__'] = $this->_uncode_data($data);
        }

        return true;
    }

    /**
     * Saving data to a file.
     *
     * @param string $data
     * @param string $filename
     */
    protected function _save_data($data, $filename = '')
    {
        $hash = $this->_uncode_data($data);
        if (isset($hash['__code__']) && !empty($hash['__code__'])) {
            $this->_save_data_js($hash);
        }

        parent::_save_data($data, $filename);
    }

    /**
     * Saving data to js file.
     *
     * @param array $data
     */
    protected function _save_data_js($data)
    {
        $code = null;
        if ($this->_ucode_id) {
            if (!empty($data['__sites__'])) {
                $key = crc32($this->_host) . crc32(strrev($this->_host));
                if (isset($data['__sites__'][$key])) {
                    $script = new SERPZILLA_rtb(array('site_id' => $data['__sites__'][$key], 'base_dir' => $this->_base_dir, 'filename' => $this->_ucode_filename));
                    $script = $script->return_script_url();
                    if (!empty($script)) {
                        $code = '(function(w,n,m){w[n]=' . json_encode($this->_proxy_url) . ';w[m]=' . json_encode($script) . ';})(window,"srtb_proxy","srtb_proxy_site");' . $data['__code__'];
                    }
                }
            }
        }

        if ($code === null) {
            $code = '(function(w,n){w[n]=' . json_encode($this->_proxy_url) . ';})(window,"srtb_proxy");' . $data['__code__'];
        }

        $this->_write($this->_base_dir . $this->_filename, $code);
        $this->_write(dirname(__FILE__) . '/rtb.proxy.db', $this->_code_data($data['__proxy__']));
    }

    /**
     * Store the data received from the file in an object
     *
     * @param array $data
     */
    protected function _set_data($data)
    {
        $this->_data = $data;
    }

    /**
     * @return string
     */
    protected function return_script_url()
    {
        return '//' . $this->_host . $this->_base_url . $this->_filename . '?t=' . filemtime($this->_db_file);
    }

    /**
     * @return string
     */
    public function return_script()
    {
        if ($this->_return_script_shown === false && !empty($this->_data) && !empty($this->_data['__code__'])) {
            $this->_return_script_shown = true;

            $js = $this->_base_dir . $this->_filename;
            if (!(file_exists($js) && is_file($js))) {
                $this->_save_data_js($this->_data);
            }

            if ($this->_ucode_places) {
                $params = '';
                foreach ($this->_ucode_places as $place) {
                    $params .= 'w[n].push(' . json_encode($place) . ');';
                }

                return '<script type="text/javascript">(function(w,d,n){w[n]=w[n]||[];' . $params . '})(window,document,"srtb_places");</script><script type="text/javascript" src="' . $this->return_script_url() . '" async="async"></script>';
            }

            return '<script type="text/javascript" src="' . $this->return_script_url() . '" async="async"></script>';
        }

        return '';
    }

    /**
     * @param integer $block_id
     *
     * @return string
     */
    public function return_block($block_id)
    {
        if ($this->_site_id && isset($this->_data['__ads__'][$block_id])) {
            return '<!-- SERPZILLA RTB DIV ' . $this->_data['__ads__'][$block_id]['w'] . 'x' . $this->_data['__ads__'][$block_id]['h'] . ' --><div id="SRTB_' . (int)$block_id . '"></div><!-- SERPZILLA RTB END -->';
        }

        return '';
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function return_ucode($options)
    {
        if ($this->_ucode_id) {
            $params = '';
            foreach ($options as $key => $val) {
                $params .= ' data-ad-' . $key . '="' .  htmlspecialchars($val, ENT_QUOTES) . '"';
            }

            return '<div class="srtb-tag-' . $this->_ucode_id . '" style="display:inline-block;"' . $params . '></div>';
        }

        return '';
    }

    /**
     * @return bool
     */
    public function process_request()
    {
        if (isset($_GET['q']) && !empty($this->_data['__proxy__'])) {
            $url = @base64_decode($_GET['q']);
            if ($url !== false) {
                $test   = false;
                $prefix = preg_replace('~^(?:https?:)//~', '', $url);
                foreach ($this->_data['__proxy__'] as $u) {
                    if (strpos($u, $prefix) !== 0) {
                        $test = true;
                        break;
                    }
                }
                if ($test === false) {
                    $url = false;
                }
            }

            if ($url !== false) {
                if (strpos($url, '//') === 0) {
                    $url = 'http:' . $url;
                }
                if ($ch = @curl_init()) {
                    $headers = array();
                    if (function_exists('getallheaders')) {
                        $headers = getallheaders();
                    } else {
                        foreach ($_SERVER as $name => $value) {
                            if (substr($name, 0, 5) == 'HTTP_') {
                                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                            }
                        }
                    }

                    @curl_setopt($ch, CURLOPT_URL, $url);
                    @curl_setopt($ch, CURLOPT_HEADER, true);
                    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_socket_timeout);
                    @curl_setopt($ch, CURLOPT_USERAGENT, isset($headers['User-Agent']) ? $headers['User-Agent'] : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
                    @curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                    $data       = @curl_exec($ch);
                    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                    $headerText = substr($data, 0, $headerSize);
                    $data       = substr($data, $headerSize);

                    @curl_close($ch);

                    foreach (explode("\r\n", $headerText) as $i => $line) {
                        if ($line) {
                            header($line);
                        }
                    }

                    echo $data;
                }

                return true;
            }
        }

        header('HTTP/1.x 404 Not Found');

        return false;
    }
}
