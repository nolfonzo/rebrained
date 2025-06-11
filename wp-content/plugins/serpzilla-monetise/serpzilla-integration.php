<?php
/*
Plugin Name: Wp Site
Plugin URI:
Description: Plugin for webmaster services integration
Version: 0.1
Author: serpzilla.com
Author URI: https://www.serpzilla.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: serpzilla-api
*/

if ( ! function_exists( 'boolval' ) ) {
    function boolval( $val ) {
        return (bool) $val;
    }
}

if ( ! function_exists( 'intval' ) ) {
    function intval( $val ) {
        return (int) $val;
    }
}

class Serpzilla_API {

    private static $_options = array(
        'serpzilla_user'             => '', // like d12d0d074c7ba7f6f78d60e2bb560e3f
        'serpzilla_part_is_client'   => true,
        'serpzilla_part_is_context'  => true,
        'serpzilla_part_is_rtb'    => false,
        'serpzilla_widget_class'     => 'advert',
        'serpzilla_login'            => ' ',
        'serpzilla_password'         => ' ',
    );

    // is `wp-content/upload` because this dir always writable
    private static $_serpzilla_path;

    private $_serpzilla_options = array(
        'charset'                 => 'UTF-8', // since WP 3.5 site encoding always utf-8
        'multi_site'              => true,
        'show_counter_separately' => true,
        'force_show_code' => false
    );

    /** @var serpzilla_client */
    private $_serpzilla_client;

    /** @var serpzilla_context */
    private $_serpzilla_context;

    private $_plugin_basename;

    private $_serpzilla_context_replace_texts;

    public function __construct() {
        $this->_plugin_basename = plugin_basename( __FILE__ );
        // misc
        load_plugin_textdomain( 'serpzilla-api', false, dirname( $this->_plugin_basename ) . '/languages' );
        register_activation_hook( __FILE__, array( __CLASS__, 'activation_hook' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivation_hook' ) );
        register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall_hook' ) );

        // init
        add_action('init', array(&$this, 'init'));

        // _SERPZILLA_USER
        if ( ! defined( '_SERPZILLA_USER' ) ) {
            define( '_SERPZILLA_USER', get_option( 'serpzilla_user' ) );
        } else {
            if ( is_admin() ) {
                add_action( 'admin_init', function () {
                    add_action( 'admin_notices', function () {
                        echo '<div class="update-nag"><p>';
                        echo sprintf('The constant %s has been already defined before!', '<code>_SERPZILLA_USER</code>');
                        echo ' ';
                        echo sprintf('Plugin %s settings are not applied!', '<code>Serpzilla.com integration</code>');
                        echo '</p></div>';
                    } );
                } );
            }
        }

        $this->_registerLinks();
        $this->_registerContext();
        //$this->_registerRTB();
        $this->_registerCounter();
    }

    protected function _registerLinks()
    {
        if ( get_option( 'serpzilla_part_is_client' ) ) {
            add_action( 'widgets_init', function () {
                register_widget( 'serpzilla_API_Widget_Links' );
            }, 1 );

            add_shortcode( 'serpzilla', array( &$this, 'shortcode_serpzilla' ) );
            add_filter( 'no_texturize_shortcodes', function ( $list ) {
                $list[] = 'serpzilla';

                return $list;
            } );
            add_action( 'wp_footer', array( &$this, 'render_remained_links' ), 1 );
        }
    }

    protected function _registerContext()
    {
        if ( get_option( 'serpzilla_part_is_context' ) && _SERPZILLA_USER !== '' ) {
            add_filter( 'the_content', array( &$this, '_serpzilla_replace_in_text_segment' ), 11, 1);
            add_filter( 'the_excerpt', array( &$this, '_serpzilla_replace_in_text_segment' ), 11, 1 );
            remove_filter( 'the_content', 'do_shortcode' );
            remove_filter( 'the_excerpt', 'do_shortcode' );
            add_filter( 'the_content', 'do_shortcode', 12 );
            add_filter( 'the_excerpt', 'do_shortcode', 12 );
        }
    }

    protected function _registerRTB()
    {
        if ( get_option( 'serpzilla_part_is_rtb' ) && _SERPZILLA_USER !== '' ) {
            add_action( 'widgets_init', function () {register_widget( 'serpzilla_API_Widget_RTB' );}, 1 );
        }
    }

    protected function _registerCounter()
    {
        if ( _SERPZILLA_USER !== '' ) {
            add_action( 'wp_footer', array( &$this, '_serpzilla_return_counter' ), 1 );
        }
    }

    public function render_remained_links() {
        //if ( $this->_getserpzillaClient()->_links_page > 0 ) {
        echo do_shortcode( '[serpzilla block=1 orientation=1]' );
        //}
    }

    public function init() {
        // admin panel
        add_action( 'admin_init', array( &$this, 'admin_init' ), 1 ); // init settings
        add_action( 'admin_menu', array( &$this, 'admin_menu' ), 1 ); // create page
        add_filter( 'plugin_action_links_' . $this->_plugin_basename, array( &$this, 'plugin_action_links' ) ); # links
        add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 1, 2 ); # plugins meta

        // show code on front page -- need to add site to serpzilla system
        if ( is_front_page() ) {
            add_action( 'wp_footer', array( &$this, '_serpzilla_return_links' ), 1 );
        }
    }

    public function upgrade($upgrader_object, $options) {
        $current_plugin_path_name = plugin_basename( __FILE__ );
        if ($options['action'] == 'update' && $options['type'] == 'plugin' ) {
            foreach($options['plugins'] as $each_plugin){
                if ($each_plugin == $current_plugin_path_name) {
                    self::activation_hook();
                }
            }
        }
    }

    public static function activation_hook() {
        // init options
        foreach ( self::$_options as $option => $value ) {
            add_option( $option, $value );
        }

        // let make dir and copy serpzilla's files to uploads/.serpzilla/
        if ( ! wp_mkdir_p( self::_getserpzillaPath() ) ) {
            $activationFailedMessage = sprintf('Directory %s is not available for write file.', '<i>`' . ABSPATH . WPINC . '/upload' . '`</i>' );
            self::chmod_wrong_on_activation($activationFailedMessage);
        }

        // let copy file to created dir
        $local_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'serpzilla';

        $files = array(
            $local_path . DIRECTORY_SEPARATOR . 'serpzilla.php' => self::_getserpzillaPath() . DIRECTORY_SEPARATOR . 'serpzilla.php',
            $local_path . DIRECTORY_SEPARATOR . '.htaccess' => self::_getserpzillaPath() . DIRECTORY_SEPARATOR . '.htaccess'
        );

        foreach ($files as $filePathFrom => $filePathTo) {
            if (!copy( $filePathFrom, $filePathTo)) {
                $activationFailedMessage = sprintf('File %s is not writable.','<i>`' . $filePathTo . '`</i>');
                self::chmod_wrong_on_activation($activationFailedMessage);
            }
        }
    }

    public static function chmod_wrong_on_activation($activationFailedMessage) {
        $path = plugin_basename( __FILE__ );
        deactivate_plugins( $path );

        $link        = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $path ), 'activate-plugin_' . $path );
        $string      = '';
        $string .= $activationFailedMessage . '.<br/>';
        $string .= sprintf('Correct and reactivate the %s plugin.', '<b>' . $path . '</b>' ) . '.<br/>';
        $string .= '<a href="' . $link . '" class="edit">Activate</a>';

        wp_die( $string );
    }

    public static function deactivation_hook() {
        // clear cache?
    }

    public static function uninstall_hook() {
        // delete options
        foreach ( self::$_options as $option => $value ) {
            delete_option( $option );
        }

        // delete serpzilla's files
        self::_deleteDir( self::_getserpzillaPath() );
    }

    private static function _deleteDir( $path ) {
        $class_func = array( __CLASS__, __FUNCTION__ );

        return is_file( $path ) ? @unlink( $path ) : array_map( $class_func, glob( $path . '/*' ) ) == @rmdir( $path );
    }

    private static function _getserpzillaPath() {
        if ( self::$_serpzilla_path === null ) {
            self::$_serpzilla_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.serpzilla';
        }

        return self::$_serpzilla_path;
    }

    private function _getserpzillaClient() {
        if ( $this->_serpzilla_client === null ) {
            include_once self::_getserpzillaPath() . DIRECTORY_SEPARATOR . 'serpzilla.php';
            $this->_serpzilla_client = new serpzilla_client( $this->_serpzilla_options );
        }

        return $this->_serpzilla_client;
    }
    private function _serpzilla_return_links( $count, $options ) {
        return $this->_getserpzillaClient()->return_links( $count, $options );
    }

    public function _serpzilla_return_counter() {
        $counterHtml = $this->_getserpzillaClient()->return_counter();

        echo $counterHtml;
    }

    private function _getserpzillaContext() {
        if ( $this->_serpzilla_context === null ) {
            include_once self::_getserpzillaPath() . DIRECTORY_SEPARATOR . 'serpzilla.php';
            $this->_serpzilla_context = new serpzilla_context( $this->_serpzilla_options );
        }

        return $this->_serpzilla_context;
    }

    public function _serpzilla_replace_in_text_segment( $text ) {
        $hash = md5($text);

        if (!isset($this->_serpzilla_context_replace_texts[$hash])) {
            $this->_serpzilla_context_replace_texts[$hash] = $this->_getserpzillaContext()->replace_in_text_segment( $text );
        }

        return $this->_serpzilla_context_replace_texts[$hash];
    }

    public function shortcode_serpzilla( $atts, $content = null ) {
        $atts = shortcode_atts( array(
                                    'count'       => null,
                                    'block'       => 0,
                                    'orientation' => 0,
                                    'force_show_code' => false
                                ), $atts );

        $this->_serpzilla_options['force_show_code'] = $atts['force_show_code'];

        $text = $this->_serpzilla_return_links(
            $atts['count'],
            array(
                'as_block'          => $atts['block'] == 1,
                'block_orientation' => $atts['orientation'],
            )
        );

        return ! empty( $text ) ? $text : $content;
    }

    public function plugin_action_links( $links ) {
        unset( $links['edit'] );
        $settings_link = '<a href="admin.php?page=page_serpzilla">Settings</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }

    public function plugin_row_meta( $links, $file ) {
        if ( $file == $this->_plugin_basename ) {
            $settings_link = '<a href="admin.php?page=page_serpzilla">Settings</a>';
            $links[]       = $settings_link;
            $links[]       = 'Code is poetry!';
        }

        return $links;
    }

    public function admin_menu() {
        // add_menu_page(
        //     'Serpzilla Settings', // title
        //     'Serpzilla - Monetise Your Site', // menu title
        //     'manage_options', // capability
        //     'page_serpzilla', // menu slug
        //     array( &$this, 'page_serpzilla' ), // callback
        //     '
        //     data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAAsTAAALEwEAmpwYAAAA IGNIUk0AAHolAACAgwAA+f8AAIDpAAB1MAAA6mAAADqYAAAXb5JfxUYAAAHMSURBVHjatJNdSFNx GMZ/5xxjpuXBjeFiGJRYfoSGCOpF4Kgpm9RFQY3wIi/0zroZpGQgfn+SCUJRemdQVEohJYKwPohU tAuZXkQhDGHzRrahR47bv5shHkOISe/1+/5enud9H0kIASBIriRJJAjJVlKAYCjMnbpnlJaeSQ6w srrOzVsjnMu1Iel6TLydWOD73C90fZecs1l4PGVYLBn0zoCmG4cVCZqqIbAWwmJJR+rseidevprD 7S7CbD7B8nIAq/Uk7Z0ebjw1uju/Bpvb8LsdsjMTHlxx9ori4tMM9Hv2GrUdnVTTMcPmLz/BMQTN 1dB2dZ+J9Q1jwu9fx+t14ajMQ1XT/tK8EYWSbsi1wsxdUOR9gFAoLHr6ppid9ROPCxyV+TTdr8Fm UwGIC3CPwI8ALDXDKfWQM0YiGj7fKoOPPqJmHGdy4h4AHR+gdQqmG+Hy+X/4g+ejnxh6PM3Xzw9Y DKbhHIYWF7TWHPJIt2ufiCrnBSrKc4hGNTq63hONaLye9JLXJrEbg4HrRt2uQjCnJwDjL76J0TEf wWAYWZYoLLDzsOUamXY7lwYhFjdulGV4Uw8Xsw9I2NraQVEUTKaU/5+Fg4AjxfnPAKvAs4ob5AOG AAAAAElFTkSuQmCC
        //     '
        // );

        add_submenu_page(
            'page_serpzilla',
            'Serpzilla Settings', // title
            'Settings', // menu title
            'manage_options', // capability
            'page_serpzilla', // menu slug
            array( &$this, 'page_serpzilla' ) // callback
        );
    }

    public function page_serpzilla() {
        ?>
      <div class="wrap">

        <h1>Serpzilla - Monetise Your Site</h1>

        <form action="options.php" method="post" novalidate="novalidate">

            <?php
            settings_fields( 'serpzilla_base' );
            do_settings_sections( 'page_serpzilla' );
            submit_button();
            ?>

        </form>

      </div>
        <?php
    }

    public function admin_init() {
        // register settings `base`
        register_setting( 'serpzilla_base', 'serpzilla_user', 'trim' );
        register_setting( 'serpzilla_base', 'serpzilla_part_is_client', 'boolval' );
        register_setting( 'serpzilla_base', 'serpzilla_part_is_context', 'boolval' );
        register_setting( 'serpzilla_base', 'serpzilla_part_is_rtb', 'boolval' );
        register_setting( 'serpzilla_base', 'serpzilla_widget_class', 'trim' );

        // add sections
        add_settings_section(
            'section__serpzilla_identification', // id
            'Set your User Key', // title
            function () {
                echo '<br/>';
            }, // callback
            'page_serpzilla' // page
        );

        add_settings_section(
            'section__serpzilla_parts', // id
            'Monetization formats', // title
            function () {
                echo 'Activate the monetization formats you need.';
                echo '<br/>';
                echo '<br/>';
            }, // callback
            'page_serpzilla' // page
        );

        // add fields
        add_settings_field(
            'serpzilla_user', // id
            'USER KEY', // title
            array( &$this, 'render_settings_field' ), // callback
            'page_serpzilla', // page
            'section__serpzilla_identification', // section
            array(
                'label_for' => 'serpzilla_user',
                'type'      => 'text',
                'descr'     =>
                    'The User Key is your unique identifier (hash). <br/>'.
                    sprintf('You can find it on the %s New Site Add Page%s in your account.','<a target="_blank" href="https://links.serpzilla.com/site.php?act=add">', '</a>') . '<br/>' .
                    sprintf('The User Key look like %s d12d0d074c7ba7f6f78d60e2bb560e3f%s. ','<b>','</b>') .
                    'Set your User Key and the plugin will do everything automatically (you will not need to upload files or archives manually).'
            ) // args
        );

        add_settings_field(
            'serpzilla_part_is_client', // id
            'Rental links', // title
            array( &$this, 'render_settings_field' ), // callback
            'page_serpzilla', // page
            'section__serpzilla_parts', // section
            array(
                'label_for' => 'serpzilla_part_is_client',
                'type'      => 'checkbox',
                'descr'     =>
                    '<br/>' .
                    sprintf('After activation, both the %s widget%s for displaying links and the shortcode will be available:', '<a target="_blank" href="' . admin_url( 'widgets.php' ) . '">', '</a>')
                    .'<br/>
                    <code>[serpzilla]</code> - displaying all links in text format<br/>
                    <code>[serpzilla force_show_code = 1]</code> - force show check-code<br/>
                    <code>[serpzilla count=2]</code> - displaying only two links<br/>
                    <code>[serpzilla count=2 block=1]</code> - displaying links in block format<br/>
                    <code>[serpzilla count=2 block=1 orientation=1]</code> - displaying links in block format horizontally<br/>
                    <code>[serpzilla] html, js[/serpzilla]</code> - displaying alternative text in the absence of links.<br/>'.
                    'To display inside your WordPress Theme (template), use the following code:'. '<code>' . esc_attr( '<?php echo do_shortcode(\'[serpzilla]\') ?>' ) . '</code>'. '.<br/>'.
                    sprintf('If you do not place all the sold links on the page, then the remaining ones will be added to the footer of the site in order to avoid the status %s for links.', '<code>ERROR</code>' )
            ,
            ) // args
        );

        add_settings_field(
            'serpzilla_part_is_context', // id
            'Content rental links', // title
            array( &$this, 'render_settings_field' ), // callback
            'page_serpzilla', // page
            'section__serpzilla_parts', // section
            array(
                'label_for' => 'serpzilla_part_is_context',
                'type'      => 'checkbox',
                'descr'     => 'Links placed inside existing page content',
            ) // args
        );
    }

    public function render_settings_field( $atts ) {
        $id    = $atts['label_for'];
        $type  = $atts['type'];
        $descr = $atts['descr'];

        switch ( $type ) {
            default:
                $form_option = esc_attr( get_option( $id ) );
                echo "<input name=\"{$id}\" type=\"{$type}\" id=\"{$id}\" value=\"{$form_option}\" class=\"regular-{$type}\" />";
                break;
            case 'checkbox':
                $checked = checked( '1', get_option( $id ), false );
                echo '<label>';
                echo "<input name=\"{$id}\" type=\"checkbox\" id=\"{$id}\" value=\"1\" {$checked} />\n";
                echo 'Activate';
                echo '</label>';
                break;
            case 'select':

                echo '<label>';
                echo "<select name=\"{$id}\" id=\"{$id}\">\n";
                foreach ($atts['options'] as $s_id => $val ){
                    $checked = selected( get_option( $id ), $s_id, false );
                    echo "<option value='$s_id' {$checked}>$val</option>";
                }
                echo "</select>";
                echo '</label>';
                break;
        }

        if ( ! empty( $descr ) ) {
            echo "<p class=\"description\">{$descr}</p>";
        }
    }

}

class serpzilla_API_Widget_Links extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'serpzilla_links',
            'Serpzilla Links',
            array(
                'description' => 'Displaying Serpzilla links on the site. You can use multiple widgets to display links in multiple locations.',
                'classname'   => '',
            )
        );
    }

    public function widget( $args, $instance ) {
        $o_count       = $instance['count'] ? ' count=' . $instance['count'] : '';
        $o_block       = $instance['block'] ? ' block=' . $instance['block'] : '';
        $o_orientation = $instance['orientation'] ? ' orientation=' . $instance['orientation'] : '';
        $o_force_show_code = $instance['force_show_code'] ? ' force_show_code=' . $instance['force_show_code'] : '';

        $shortcode = "[serpzilla{$o_count}{$o_block}{$o_orientation}{$o_force_show_code}]{$instance['content']}[/serpzilla]";

        $text = do_shortcode( $shortcode );

        if ( $text === '' || $text === $shortcode ) {
            $text = $instance['content'];
        }

        if ( ! empty( $text ) ) {
            echo $args['before_widget'];

            if ( ! empty( $instance['title'] ) ) {
                echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
            }

            echo $text;

            echo $args['after_widget'];
        }
    }

    public function form( $instance ) {
        $instance = wp_parse_args(
            (array) $instance,
            array( 'title' => '', 'block' => '0', 'count' => '', 'orientation' => '0', 'content' => '',
                   'force_show_code' => '' )
        );
        ?>

      <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>">
            <?php echo 'Title:'; ?>
        </label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
               name="<?php echo $this->get_field_name( 'title' ); ?>"
               type="text"
               value="<?php echo esc_attr( $instance['title'] ); ?>">
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'count' ); ?>">
            <?php echo 'Number of links:'; ?>
        </label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>"
               name="<?php echo $this->get_field_name( 'count' ); ?>"
               type="number"
               value="<?php echo esc_attr( $instance['count'] ); ?>">
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'block' ); ?>">
            <?php echo 'Format:'; ?>
        </label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'block' ); ?>"
                name="<?php echo $this->get_field_name( 'block' ); ?>">
          <option value="0"<?php selected( $instance['block'], '0' ); ?>>
              <?php echo 'Text'; ?>
          </option>
          <option value="1"<?php selected( $instance['block'], '1' ); ?>>
              <?php echo 'Block'; ?>
          </option>
        </select>
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'orientation' ); ?>">
            <?php echo 'Block orientation'; ?>
        </label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'orientation' ); ?>"
                name="<?php echo $this->get_field_name( 'orientation' ); ?>">
          <option value="0"<?php selected( $instance['orientation'], '0' ); ?>>
              <?php echo 'Vertical'; ?>
          </option>
          <option value="1"<?php selected( $instance['orientation'], '1' ); ?>>
              <?php echo 'Horizontally'; ?>
          </option>
        </select>
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'content' ); ?>">
            <?php echo 'Alternative text:'; ?>
        </label>
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'content' ); ?>"
                  name="<?php echo $this->get_field_name( 'content' ); ?>"
        ><?php echo esc_attr( $instance['content'] ); ?></textarea>
      </p>

      <p>
        <input class="widefat" type="checkbox" id="<?php echo $this->get_field_id( 'force_show_code' ); ?>"
               <?php checked('on', $instance['force_show_code']);?>
               name="<?php echo $this->get_field_name( 'force_show_code' ); ?>">
        <label for="<?php echo $this->get_field_id( 'force_show_code' ); ?>">
            <?php echo 'Force show check-code'; ?>
        </label>
      </p>

        <?php
    }
}

class serpzilla_API_Widget_RTB extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'serpzilla_rtb',
            'serpzilla RTB',
            array(
                'description' => 'Output of Display Advertising on the site (RTB ad blocks). You can use multiple widgets to display in multiple locations.',
                'classname'   => 'advert_rtb',
            )
        );
    }

    public function widget( $args, $instance ) {

        $text = $instance['html'];

        if ( ! empty( $text ) ) {
            echo $args['before_widget'];

            if ( ! empty( $instance['title'] ) ) {
                echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
            }

            echo $text;

            echo $args['after_widget'];
        }
    }

    public function form( $instance ) {
        $instance = wp_parse_args(
            (array) $instance,
            array( 'title' => '', 'count' => '' )
        );
        ?>

      <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>">
            <?php echo 'Title:'; ?>
        </label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
               name="<?php echo $this->get_field_name( 'title' ); ?>"
               type="text"
               value="<?php echo esc_attr( $instance['title'] ); ?>">
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'html' ); ?>">
            <?php echo 'RTB code'; ?>
        </label>
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'html' ); ?>"
                  name="<?php echo $this->get_field_name( 'html' ); ?>"
                  rows="3"
        ><?php echo esc_attr( $instance['html'] ); ?></textarea>
      </p>


        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $new_instance['count']       = (int) $new_instance['count'];
        return $new_instance;
    }
}

$serpzilla_api = new Serpzilla_API();