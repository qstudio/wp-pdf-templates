<?php
/**
 * Plugin Name: Wordpress PDF Templates
 * Plugin URI: https://github.com/Seravo/wp-pdf-templates
 * Description: This plugin utilises the DOMPDF Library to provide a URL endpoint e.g. /my-post/pdf/ that generates a downloadable PDF file.
 * Version: 1.4.0
 * Author: Seravo Oy
 * Author URI: http://seravo.fi
 * License: GPLv3
*/


/**
 * Copyright 2014 Seravo Oy
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * Wordpress PDF Templates
 *
 * This plugin utilises the DOMPDF Library to provide a simple URL endpoint
 * e.g. http://my-site.com/my-post/pdf/ that generates a downloadable PDF file.
 *
 * If pretty permalinks are disabled. GET parameters (e.g. ?p=1&pdf) can be used
 * instead.
 *
 * The PDF output can be customized by copying the index-pdf.php file from
 * the plugin directory to your theme and creating your own custom template for
 * PDF prints.
 *
 * Stylesheets used on the site are disabled by default, but you can define your
 * own stylesheets within the pdf-template.php file. PDF Templates can be
 * previewed as raw HTML at the /pdf-preview URL endpoint.
 *
 * For further information see readme.txt
 */


// quick check :) ##
defined( 'ABSPATH' ) OR exit;

/* Plugin Class */
if ( ! class_exists( 'WP_PDF_Templates' ) ) 
{
    
    // on plugin activation ##
    register_activation_hook( __FILE__, array( 'WP_PDF_Templates', 'flush_pdf_rewrite_rules' ) );
    register_activation_hook( __FILE__, array( 'WP_PDF_Templates', 'set_dompdf_fonts' ) );
    
    // on plugin deactivation ##
    register_deactivation_hook( __FILE__, array( 'WP_PDF_Templates', 'flush_pdf_rewrite_rules' ) );
    
    // plugin uninstall handled by uninstall.php ##
    
    // instatiate plugin via WP action - not too early, not too late ##
    add_action( 'init', array ( 'WP_PDF_Templates', 'get_instance' ), 0 );
    
    // declare the class and base Class ##
    class WP_PDF_Templates 
    {
        
        // Refers to a single instance of this class. ##
        private static $instance = null;
        
        // public properties ##
        public static $pdf_post_types = '';
        public static $query_vars = array();
        
        // for translation ##
        const text_domain = 'wp-pdf-templates';
        
        /**
         * Creates or returns an instance of this class.
         *
         * @since       1.4.0
         * @return  Foo     A single instance of this class.
         */
        public static function get_instance() {

            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;

        }
        
        
        /**
         * Instatiate Class
         * 
         * @since       1.4.0
         * @return      void
         */
        private function __construct() 
        {
            
            // text-domain ##
            add_action ( 'load_plugin_textdomain', array( 'WP_PDF_Templates', 'load_plugin_textdomain' ), 1 );
            
            // define constants ##
            add_action( 'init', array( 'WP_PDF_Templates', 'define_constants' ), 1 );
            
            // define proerties ##
            add_action( 'init', array( 'WP_PDF_Templates', 'define_properties' ), 2 );
            
            // define post type support ##
            add_action( 'init', array( 'WP_PDF_Templates', 'set_post_types' ), 3 );
            
            // add re-write rules ##
            add_action( 'init', array( 'WP_PDF_Templates', 'pdf_rewrite' ), 1000 );
            
            // get query variables ##
            add_filter( 'query_vars', array( 'WP_PDF_Templates', 'get_pdf_query_vars') );
            
            // template redirect ##
            add_action( 'template_redirect', array( 'WP_PDF_Templates', 'template_redirect' ) );
            
            // process html template used for PDF ##
            add_filter( 'pdf_template_html', array( 'WP_PDF_Templates', 'process_pdf_template_html' ) );
            
            // run on post update - to allow for cache clearing ##
            add_action( 'save_post', array( 'WP_PDF_Templates', 'clear_cache' ) );
            
        }
        
        
        /**
         * Load Text Domain for translations
         * 
         * @since       1.4.0
         * @return      void
         */
        public static function load_plugin_textdomain() 
        {
            
            // set text-domain ##
            $domain = self::text_domain;
            
            // The "plugin_locale" filter is also used in load_plugin_textdomain()
            $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

            // try from global WP location first ##
            load_textdomain( $domain, WP_LANG_DIR.'/plugins/'.$domain.'-'.$locale.'.mo' );
            
            // try from plugin last ##
            load_plugin_textdomain( $domain, FALSE, plugin_dir_path( __FILE__ ).'languages/' );
            
        }
        
        
        
        /**
         * Define Constants
         * 
         * @since       1.4.0
         * @return      void
         */
        public static function define_constants()
        {
            
            // doing PDF routine ##
            define( 'DOING_PDF', true );
            
            // get upload directory ##
            $upload_dir = wp_upload_dir();
            
            /*
            * Option to disable PDF caching
            *
            * This can be used for rapidly changing content that's uncacheable, such as
            * dynamically generated feeds or user-tailored views.
            */
            if ( ! defined( 'DISABLE_PDF_CACHE' ) ) {
                
                define( 'DISABLE_PDF_CACHE', false );
                
            }

            /*
             * Option to enable cookies on fetching the PDF template HTML.
             *
             * This might be useful if the content or access to it depends on browser
             * cookies. A possible use scenario for this could be when a login
             * authentification is required to access the content.
             */
            if ( ! defined( 'FETCH_COOKIES_ENABLED' ) ) {
                
                define( 'FETCH_COOKIES_ENABLED', false );
                
            }
                
            /**
             * PDF debugging
             */
            if ( ! defined( 'WP_PDF_TEMPLATES_DEBUG' ) ) {

                define( 'WP_PDF_TEMPLATES_DEBUG', false );
                
            }
            
            // Set PDF file cache directory ##
            if ( ! defined( 'PDF_CACHE_DIRECTORY' ) ) {
                
                define('PDF_CACHE_DIRECTORY', $upload_dir['basedir'] . '/pdf-cache/');
                
            }

            // Allow remote assets in docs ##
            if ( ! defined( 'DOMPDF_ENABLE_REMOTE' ) ) {
                
                define( 'DOMPDF_ENABLE_REMOTE', true );
                
            }

            // Redefine font directories ##
            if ( ! defined( 'DOMPDF_FONT_DIR' ) ) {
                
                define( 'DOMPDF_FONT_DIR', $upload_dir['basedir'] . '/dompdf-fonts/' );
                
            }

            // font caching ##
            if ( ! defined( 'DOMPDF_FONT_CACHE' ) ) {
                
                define( 'DOMPDF_FONT_CACHE', $upload_dir['basedir'] . '/dompdf-fonts/' );
                
            }
            
        }
        
        
        /**
         * Define Class Properties
         * 
         * @since       1.4.0
         * @return      void
         */
        public static function define_properties()
        {
            
            // print post types ##
            self::$pdf_post_types = apply_filters( 'wp_pdf_templates_post_types', array( 'post', 'page' ) );
            
            // query variables -- should this be filterable? ##
            self::$query_vars[] = 'pdf';
            self::$query_vars[] = 'pdf-preview';
            self::$query_vars[] = 'pdf-template';
            
        }
        
        
        
        /**
         * This function can be used to set PDF print support for custom post types.
         * Takes an array of post types (strings) as input. See defaults below.
         * 
         * @since       1.4.0
         * @return      void
         */
        public static function set_post_types( $post_types = null ) 
        {
            
            // sanity check ##
            if ( is_null ( $post_types ) || ! is_array ( $post_types ) ) { 
                
                return false; 
                
            }
            
            self::$pdf_post_types = $post_types;
                
        }

        
        
        /**
         * Adds rewrite rules for printing if using pretty permalinks
         * 
         * @since       1.4.0
         * @return      void
         */
        public static function pdf_rewrite() 
        {

            add_rewrite_endpoint( 'pdf', EP_ALL );
            add_rewrite_endpoint( 'pdf-preview', EP_ALL );
            add_rewrite_endpoint( 'pdf-template', EP_ALL );

        }

        
        /**
         * Flushes the rewrite rules on plugin activation and deactivation
         * NOTE: You can also do this by going to Settings > Permalinks and hitting the save button
         * 
         * @since 1.4.0
         * @return      void
         */
        public static function flush_pdf_rewrite_rules() 
        {

             // flush rewrite rules
             global $wp_rewrite;

             self::pdf_rewrite();

             $wp_rewrite->flush_rules(false);

        }
       
       
        /**
         * Add plugin Query Variables
         * 
         * @since       1.4.0
         * @return      Array       WP Query variables
         */
        public static function get_pdf_query_vars( $query_vars ) 
        {

             return apply_filters( 'wp_pdf_templates_query_vars', array_merge( $query_vars, self::$query_vars ) );
         
        }
        
        
        
        /**
         * Generate PDF file names for generation and cache clearance
         * 
         * @since       1.4.0
         * @return      String      PDF file name
         */
        public static function get_pdf_title( $post_id = null )
        {
            
            // sanity check ##
            if ( is_null ( $post_id ) ) { return false; }
            
            // grab post ##
            $post = get_post( $post_id );
            
            if ( ! $post ) { return false; }
            
            // check of this post has a post_meta field ##
            $post_meta == get_post_meta( $post_id, '_wp_pdf_templates_title', true );
            if ( $post_meta ) {
                
                if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) pr( $post_meta );
                return $post_meta;
                
            }
            
            // prepare WP functions ##
            setup_postdata( $post );
            
            // get file published date ##
            $date = get_the_date( "d-m-Y" );
            if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) pr( $date );
            
            // get the post title ##
            $title = sanitize_title( get_the_title() );
            if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) pr( $title );
            
            // kick back a clean title ##
            return apply_filters( 'wp_pdf_templates_title', $title . '-' . $date . '.pdf' );
            
        }
        
        
        /**
         * Delete stored version of PDF on post update
         * 
         * @since       1.4.0
         * @return      void
         */
        public static function clear_cache( $post_id = null ) 
        {
            
            // sanity check ##
            if ( is_null ( $post_id ) ) { return false; }
            
            // If this is just a revision, don't clear cache ##
            if ( wp_is_post_revision( $post_id ) ) {
		
                return false;
            
            }
            
            // create unique hash ##
            $cached = PDF_CACHE_DIRECTORY . self::get_pdf_title( $post_id );
            
            // check if the cached file exists, if so - delete it ##
            if ( 
                file_exists( $cached ) 
            ) {
                
                @unlink( $cached );
                if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) pr( "file found: ".$cached );
                
            }
            
        }
        
        
        
        
        /**
         * Creates a directory for any new fonts the user may upload
         * 
         * @since       1.4.0
         * @return      void
         */
        function set_dompdf_fonts()
        {
            
            // copy DOMPDF fonts to wp-content/dompdf-fonts/ ##
            require_once "dompdf/dompdf_config.custom.inc.php";
            
            if( ! is_dir(DOMPDF_FONT_DIR ) ) {
                @mkdir(DOMPDF_FONT_DIR);
            }
            
            if( ! file_exists(DOMPDF_FONT_DIR . '/dompdf_font_family_cache.dist.php' ) ) {
              copy(
                dirname(__FILE__) . '/dompdf/lib/fonts/dompdf_font_family_cache.dist.php',
                DOMPDF_FONT_DIR . '/dompdf_font_family_cache.dist.php'
                );
            }
            
        }
        
        
        
        /*
         * Applies print templates
         * 
         * @since       1.4.0
         */
        public static function template_redirect() 
        {

            global $wp_query;

            if ( in_array( get_post_type(), self::$pdf_post_types ) ) {

                if ( isset( $wp_query->query_vars['pdf-template'] ) ) {
                    
                    // Substitute the PDF printing template ##
                    if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) self::pr( 'removing filters...' );

                    // disable scripts and stylesheets
                    // NOTE: We do this because in most cases the stylesheets used on the site
                    // won't automatically work with the DOMPDF Library. This way you have to
                    // define your own PDF styles using <style> tags in the template.
                    add_action( 'wp_print_styles', array ( 'WP_PDF_Templates', 'remove_dep_arrays' ), ~PHP_INT_MAX );
                    add_action( 'wp_print_scripts', array ( 'WP_PDF_Templates', 'remove_dep_arrays' ), ~PHP_INT_MAX );
                    add_action( 'wp_print_footer_scripts', array ( 'WP_PDF_Templates', 'remove_dep_arrays' ), ~PHP_INT_MAX );

                    // disable the wp admin bar ##
                    add_filter( 'show_admin_bar', '__return_false' );
                    remove_action( 'wp_head', array ( 'WP_PDF_Templates', 'admin_bar_bump_cb' ) );

                    // use the print template ##
                    add_filter( 'template_include', array ( 'WP_PDF_Templates', 'locate_pdf_template' ) );

                } else {

                    if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) self::pr( 'NOT removing filters...' );

                }

                // our post permalink
                $link = get_the_permalink();
                
                // add 'pdf-template' query variable ##
                $link = $link . ( strpos($link, '?' ) === false ? '?' : '&' ) . 'pdf-template';

                if( isset( $wp_query->query_vars['pdf']) || isset($wp_query->query_vars['pdf-preview'] ) ) {

                    if( defined('FETCH_COOKIES_ENABLED') && FETCH_COOKIES_ENABLED ) {

                    /*
                    // we want a html template
                    $header = 'Accept:text/html' . "\n";

                    // pass cookies from current request
                    if( isset( $_SERVER['HTTP_COOKIE'] ) ) {
                      $header .= 'Cookie: ' . $_SERVER['HTTP_COOKIE'] . "\n";
                    }

                    #self::pr( $header ); exit;

                    // create a request context for file_get_contents
                    $context = stream_context_create( array(
                      'http' => array(
                        'method' => 'GET',
                        'header' => $header,
                      )
                    ));
                    */

                    // we want a html template
                    $header = 'Accept:text/html' . "\n";

                    if ( isset( $_COOKIE ) ) {

                        $header .= "Cookie: wordpress_test_cookie=WP+Cookie+check; ";

                        foreach ( $_COOKIE as $key => $cookie ) {

                            if ( strpos( $key, 'wordpress_logged_in') !== FALSE ) {

                                $header .= "{$key}={$cookie}; ";

                            } else if ( strpos( $key, 'pll_language') !== FALSE ) {

                                $header .= "{$key}={$cookie}";

                            } 

                        }

                        // line=break at end of cookie headers ##
                        $header .= ' \n';

                    }

                    // Create a stream
                    $opts = array (
                        'http'          => array (
                            'method'    =>  'GET',
                            'header'    =>  $header
                        )
                    );

                    $context = stream_context_create( $opts );

                    // load the generated html from the template endpoint
                    $html = @file_get_contents(
                                $link, 
                                false, 
                                $context
                            );

                    } else {

                        // request without cookies
                        $html = @file_get_contents( $link );

                    }

                    // process the html output - allow for filtering ##
                    $html = apply_filters( 'wp_pdf_templates_html', $html );

                    // debug ##
                    if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) {

                        self::pr( $wp_query->query_vars );
                        self::pr( $link );
                        self::pr( $header );
                        self::pr( $opts );
                        self::pr( $context );
                        self::pr( $http_response_header );

                    }

                    // no html - stop here ##
                    if ( false === $html ) {

                        exit;

                    }

                    // pass for printing ##
                    self::render_pdf( $html );

                }

            }

        }

        
        
        

        /**
         * Locates the theme pdf template file to be used
         * 
         * @since       1.4.0
         * @todo        Perhaps a switch or other template selection method might be more flexible
         */
        public static function locate_pdf_template( $template = null ) 
        {
            
            // default $template_path ##
            $template_path = plugin_dir_path(__FILE__) . 'index-pdf.php';
            
            // sanity check on passed value ##
            if ( is_null ( $template ) ) { return $template_path; }
            
            // locate proper template file
            // NOTE: this only works if the standard template file exists as well
            // i.e. to use single-product-pdf.php you must also have single-product.php
            $pdf_template = str_replace( '.php', '-pdf.php', basename( $template ) );

            if( file_exists( get_stylesheet_directory() . '/' . $pdf_template ) ) {
                
                $template_path = get_stylesheet_directory() . '/' . $pdf_template;
                
            } else if ( file_exists( get_template_directory() . '/' . $pdf_template ) ) {
                
                $template_path = get_template_directory() . '/' . $pdf_template;
                
            } else if ( file_exists( plugin_dir_path(__FILE__) . $pdf_template ) ) {
                
                $template_path = plugin_dir_path(__FILE__) . $pdf_template;
                
            } else if( file_exists( get_stylesheet_directory() . '/' . 'index-pdf.php' ) ) {
                
                $template_path = get_stylesheet_directory() . '/' . 'index-pdf.php';
                
            } else if( file_exists( get_template_directory() . '/' . 'index-pdf.php' ) ) {
                
                $template_path = get_template_directory() . '/' . 'index-pdf.php';
                
            }
            
            // debug ##
            if ( defined( 'WP_PDF_TEMPLATES_DEBUG' ) && WP_PDF_TEMPLATES_DEBUG ) {
            
                self::pr( $template_path );
                
            }
            
            // kick back the template path ##
            return $template_path;

        }

        
        
        /**
         * Removes all scripts and stylesheets
         * 
         * @since       1.4.0
         */
        public static function remove_dep_arrays() 
        {
            
            // grab global variables ##
            global $wp_scripts, $wp_styles;
          
            // empty - no need to return, as global values updated ##
            $wp_scripts = $wp_styles = array();
          
        }
        
        
        
        
        /**
         * Filters the html generated from the template for printing
         * 
         * @since       1.4.0
         */
        public static function process_pdf_template_html( $html = null ) 
        {
            
            // sanity check ##
            if ( is_null ( $html ) ) { return false; }
            
            // relative to absolute links ##
            $html = preg_replace( '/src\s*=\s*"\//', 'src="' . home_url('/'), $html );
            $html = preg_replace( '/src\s*=\s*\'\//', "src='" . home_url('/'), $html );
            
            // return and allow for filtering ##
            return apply_filters( 'wp_pdf_templates_template_html', $html );
          
        }
        
        
        /**
         * Handles the PDF Conversion
         * 
         * @since       1.4.0
         */
        public static function render_pdf( $html = null ) 
        {

            // stop here, if HTML is empty ##
            if ( is_null ( $html ) || false === $html  || '' == $html ) { return false; } 

            global $wp_query, $post;

            // convert to PDF
            if ( isset( $wp_query->query_vars['pdf'] ) && $post ) {
                
                // get post title ##
                $filename = get_the_title() . '.pdf';
                
                // get pdf file name ##
                $pdf_name = self::get_pdf_title( $post->ID );
                
                // get path + file name ##
                $cached = PDF_CACHE_DIRECTORY . $pdf_name;
                
                // store pdf title to post_meta ##
                $save = add_post_meta( $post->ID, '_wp_pdf_templates_title', $pdf_name, true );
                #pr( $save );
                
                // check if we need to generate PDF against cache ##
                if ( ( 
                    defined( 'DISABLE_PDF_CACHE' ) 
                    && DISABLE_PDF_CACHE ) 
                    || ( 
                        isset( $_SERVER['HTTP_PRAGMA'] ) 
                        && $_SERVER['HTTP_PRAGMA'] == 'no-cache' 
                    ) 
                    || ! file_exists( $cached ) 
                ) {

                    // we may need more than 30 seconds execution time ##
                    set_time_limit(60);

                    // include the library ##
                    require_once plugin_dir_path(__FILE__) . 'dompdf/dompdf_config.inc.php';

                    // html to pdf conversion ##
                    $dompdf = new DOMPDF();
                    $dompdf->set_paper(
                        defined( 'DOMPDF_PAPER_SIZE' ) ? DOMPDF_PAPER_SIZE : DOMPDF_DEFAULT_PAPER_SIZE,
                        defined( 'DOMPDF_PAPER_ORIENTATION' ) ? DOMPDF_PAPER_ORIENTATION : 'portrait'
                    );
                    $dompdf->load_html($html);
                    $dompdf->set_base_path(get_stylesheet_directory_uri());
                    $dompdf->render();

                    // just stream the PDF to user if caches are disabled ##
                    if( defined( 'DISABLE_PDF_CACHE' ) && DISABLE_PDF_CACHE ) {

                        return $dompdf->stream( $filename, array( "Attachment" => false ) );
                        
                    }

                    // create PDF cache if one doesn't yet exist ##
                    if( ! is_dir( PDF_CACHE_DIRECTORY ) ) {

                        @mkdir( PDF_CACHE_DIRECTORY );

                    }

                    // save the pdf file to cache ##
                    file_put_contents( $cached, $dompdf->output() );

                }

                // read and display the cached file ##
                header('Content-type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: ' . filesize($cached));
                header('Accept-Ranges: bytes');
                readfile( $cached );

            } else {

                // print the HTML raw ##
                echo $html;

            }

            // kill php after output is complete ##
            die();

        }
        
        
        
        /**
         * Pretty print_r / var_dump
         * 
         * @since       1.4.0
         * 
         * @param       Mixed       $var        PHP variable name to dump
         * @param       string      $title      Optional title for the dump
         * @return      String      HTML output
         */
        public static function pr( $var = null, $title = null ) 
        { 

            // sanity check ##
            if ( is_null ( $var ) ) { return false; }

            // add a title to the dump ? ##
            if ( $title ) $title = '<h2>'.$title.'</h2>';

            // print it out ##
            print '<pre class="var_dump">'; echo $title; var_dump($var); print '</pre>'; 

        }
        
        
    }


}


/**
 * Function to set post type support from functions.php or other plugins
 */
function set_pdf_print_support( $post_types = null )
{
    
    // check if the passed argument is set and an array ##
    if ( is_null ( $post_types ) || ! is_array ( $post_types ) ) { 
        
        trigger_error( __( 'Supplied arguments need to be an Array.', WP_PDF_Templates::text_domain ) );
        
    }
    
    // add pdf print support to passed post types ##
    add_action( 'wp_loaded', function() use ( $post_types ) {
        
        WP_PDF_Templates::set_post_types( $post_types );
        
    }, 1 );
        
}

