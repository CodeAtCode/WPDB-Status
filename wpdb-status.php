<?php

define( 'DB_PREFIX', 'wp_' );
echo '<html>
  <head>
    <title>WordPress Database status</title>
    <h1>DB Autoload Status</h1>
    </head>
  <body>';

/**
 *
 * Based on Safe Search and Replace on Database with Serialized Data v3.1.0
 *
 */

// always good here
header( 'HTTP/1.1 200 OK' );
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

class al_tool {

	/**
	 * @var string Root path of the CMS
	 */
	public $path;

	public $connection;

	public $is_wordpress = false;

	public function __construct() {

		// discover environment
		if ( $this->is_wordpress() ) {

			// prevent warnings if the charset and collate aren't defined
			if ( !defined( 'DB_CHARSET') ) {
				define( 'DB_CHARSET', 'utf8' );
			}
			if ( !defined( 'DB_COLLATE') ) {
				define( 'DB_COLLATE', '' );
			}

			// populate db details
			$name 		= DB_NAME;
			$user 		= DB_USER;
			$pass 		= DB_PASSWORD;

			// Port and host need to be split apart.
			if ( strstr( DB_HOST, ':' ) !== false ) {
				$parts = explode( ':', DB_HOST );
				$host = $parts[0];
				$port_input = $parts[1];

				$port = abs( (int)$port_input );
			} else {
				$host = DB_HOST;
				$port = 3306;
			}

			$charset 	= DB_CHARSET;
			$collate 	= DB_COLLATE;

			$this->connection = mysqli_connect( $host, $user, $pass, $name, $port );

			// unset if not available
           if ( ! is_object( $this->connection ) ) {
                die( mysqli_connect_error() );
            }

            echo '<h2>Options in Autoload: ';
            $transient = $this->db_query( "SELECT * FROM `" . DB_PREFIX . "options` WHERE `autoload` = 'yes'" );
            echo '<span style="color:red;">' . $transient->num_rows . '</span>';
            echo '</h2>';

            echo '<h2>DB Transient in Autoload: ';
            $transient = $this->db_query( "SELECT * FROM `" . DB_PREFIX . "options` WHERE `autoload` = 'yes' AND `option_name` LIKE '%transient%'" );
            echo '<span style="color:red;">' . $transient->num_rows . '</span>';
            echo '</h2>';

            echo 'To remove expired transient:';
            echo '<pre><code>wp transient delete --expired</code></pre>';

            echo '<h2>DB Transient size</h2>';
            echo '<table><tr><td><b>Meta Key</b></td><td><b>Value</b></td></tr>';
            $this->autoload_size();
            echo '</table>';

            echo '<h2>WP User session: ';
            $session = $this->db_query( "SELECT * FROM `" . DB_PREFIX . "options` WHERE `option_name` LIKE '_wp_session_%'" );
            echo '<span style="color:red;">' . $session->num_rows . '</span>';
            echo '</h2>';

			echo '<h2>Woocommerce Log: ';
			$posts = $this->db_query( "SELECT COUNT(*) FROM " . DB_PREFIX . "woocommerce_log" );
			echo '<span style="color:red;">' . $posts->num_rows . '</span>';
			echo '</h2>';

            echo '<hr><h1>WP Extra</h1>';

            echo 'Remember to delete post in trash!';
            echo '<pre><code>wp post delete $(wp post list --post_status=trash --format=ids)</code></pre>';

            echo '<h2>WP Post without title: ';
            $posts = $this->db_query( "SELECT ID FROM " . DB_PREFIX . "posts WHERE post_title='' AND post_status!='auto-draft' AND post_status!=\'draft\' AND post_status!=\'trash\' AND (post_type='post' OR post_type='page')" );
            echo '<span style="color:red;">' . $posts->num_rows . '</span>';
            echo '</h2>';

            echo 'Empty Post Title:';
            echo '<pre><code>SELECT ID FROM ' . DB_PREFIX . 'posts WHERE post_title=\'\' AND post_status!=\'draft\' AND post_status!=\'trash\' AND post_status!=\'auto-draft\' AND (post_type=\'post\' OR post_type=\'page\');</code></pre>';
            echo '<pre><code>DELETE FROM ' . DB_PREFIX . 'posts WHERE post_title=\'\' AND post_status!=\'draft\' AND post_status!=\'trash\' AND post_status!=\'auto-draft\' AND (post_type=\'post\' OR post_type=\'page\')</code></pre>';

            echo '<h2>WP Post without content: ';
            $posts = $this->db_query( "SELECT ID FROM " . DB_PREFIX . "posts WHERE post_content='' AND post_status!='draft' AND post_status!='trash' AND post_status!='auto-draft' AND (post_type='post' OR post_type='page')" );
            echo '<span style="color:red;">' . $posts->num_rows . '</span>';
            echo '</h2>';

            echo 'Empty Post Content:';
            echo '<pre><code>SELECT ID FROM ' . DB_PREFIX . 'posts WHERE post_content=\'\' AND post_status!=\'draft\' AND post_status!=\'trash\' AND post_status!=\'auto-draft\' AND (post_type=\'post\' OR post_type=\'page\')</code></pre>';

            mysqli_close($this->connection);
		}

	}

	/**
	 * Attempts to detect a WordPress installation and bootstraps the environment with it
	 *
	 * @return bool    Whether it is a WP install and we have database credentials
	 */
	public function is_wordpress() {
		$bootstrap_file = 'wp-blog-header.php';

		if ( file_exists( dirname( __FILE__ ) . "/wp-config.php" ) ) {

			// store WP path
			$this->path = dirname( __FILE__ ) ;

			// just in case we're white screening
			try {
				// need to make as many of the globals available as possible or things can break
				// (globals suck)
				global $wp, $wpdb, $wp_query, $wp_the_query, $wp_version,
					   $wp_db_version, $tinymce_version, $manifest_version,
					   $required_php_version, $required_mysql_version,
					   $post, $posts, $wp_locale, $authordata, $more, $numpages,
					   $currentday, $currentmonth, $page, $pages, $multipage,
					   $wp_rewrite, $wp_filesystem, $blog_id, $request,
					   $wp_styles, $wp_taxonomies, $wp_post_types, $wp_filter,
					   $wp_object_cache, $query_string, $single, $post_type,
					   $is_iphone, $is_chrome, $is_safari, $is_NS4, $is_opera,
					   $is_macIE, $is_winIE, $is_gecko, $is_lynx, $is_IE,
					   $is_apache, $is_iis7, $is_IIS;

				// prevent multisite redirect
				define( 'WP_INSTALLING', true );

				// prevent super/total cache
				define( 'DONOTCACHEDB', true );
				define( 'DONOTCACHEPAGE', true );
				define( 'DONOTCACHEOBJECT', true );
				define( 'DONOTCDN', true );
				define( 'DONOTMINIFY', true );

				// bootstrap WordPress
				require( dirname( __FILE__ ) . "/{$bootstrap_file}" );

				return true;

			} catch( Exception $error ) {

				// try and get database values using regex approach
				$db_details = $this->define_find( $this->path . '/wp-config.php' );

				if ( $db_details ) {

					define( 'DB_NAME', $db_details[ 'name' ] );
					define( 'DB_USER', $db_details[ 'user' ] );
					define( 'DB_PASSWORD', $db_details[ 'pass' ] );
					define( 'DB_HOST', $db_details[ 'host' ] );
					define( 'DB_CHARSET', $db_details[ 'char' ] );
					define( 'DB_COLLATE', $db_details[ 'coll' ] );

					// additional error message
					die( 'WordPress detected but could not bootstrap environment. There might be a PHP error, possibly caused by changes to the database' );

				}

				if ( $db_details )
					return true;

			}

		}

		return false;
	}

	public function autoload_size() {
		$autoloaded = $this->db_query( "SELECT 'Autoloaded data in KiB' as name, ROUND(SUM(LENGTH(option_value))/ 1024) as value FROM " . DB_PREFIX . "options WHERE autoload='yes' UNION SELECT 'Autoloaded data count', count(*) FROM " . DB_PREFIX . "options WHERE autoload='yes' UNION (SELECT option_name, length(option_value) FROM " . DB_PREFIX . "options WHERE autoload='yes' ORDER BY length(option_value) DESC LIMIT 10)" );
		if ( is_object( $autoloaded ) ) {
		    while ($row = $autoloaded->fetch_assoc()) { 
			?>
			<tr>
			    <td><?php echo $row["name"]; ?></td>
			    <td><?php echo $row["value"]; ?> KB</td>
			</tr>
			<?php 
		    }
		}
	}

	/**
	 * Search through the file name passed for a set of defines used to set up
	 * WordPress db access.
	 *
	 * @param string $filename The file name we need to scan for the defines.
	 *
	 * @return array    List of db connection details.
	 */
	public function define_find( $filename = 'wp-config.php' ) {

		if ( $filename == 'wp-config.php' ) {
			$filename = dirname( __FILE__ ) . '/' . basename( $filename );

			// look up one directory if config file doesn't exist in current directory
			if ( ! file_exists( $filename ) )
				$filename = dirname( __FILE__ ) . '/../' . basename( $filename );
		}

		if ( file_exists( $filename ) && is_file( $filename ) && is_readable( $filename ) ) {
			$file = @fopen( $filename, 'r' );
			$file_content = fread( $file, filesize( $filename ) );
			@fclose( $file );
		}

		preg_match_all( '/define\s*?\(\s*?([\'"])(DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|DB_CHARSET|DB_COLLATE)\1\s*?,\s*?([\'"])([^\3]*?)\3\s*?\)\s*?;/si', $file_content, $defines );

		if ( ( isset( $defines[ 2 ] ) && ! empty( $defines[ 2 ] ) ) && ( isset( $defines[ 4 ] ) && ! empty( $defines[ 4 ] ) ) ) {
			foreach( $defines[ 2 ] as $key => $define ) {

				switch( $define ) {
					case 'DB_NAME':
						$name = $defines[ 4 ][ $key ];
						break;
					case 'DB_USER':
						$user = $defines[ 4 ][ $key ];
						break;
					case 'DB_PASSWORD':
						$pass = $defines[ 4 ][ $key ];
						break;
					case 'DB_HOST':
						$host = $defines[ 4 ][ $key ];
						break;
					case 'DB_CHARSET':
						$char = $defines[ 4 ][ $key ];
						break;
					case 'DB_COLLATE':
						$coll = $defines[ 4 ][ $key ];
						break;
				}
			}
		}

		return array(
			'host' => $host,
			'name' => $name,
			'user' => $user,
			'pass' => $pass,
			'char' => $char,
			'coll' => $coll
		);
	}

    public function db_query( $query ) {
		return mysqli_query( $this->connection, $query );
	}

}

// initialise
new al_tool();

?>

<br>
Check <a href="https://kinsta.com/learn/speed-up-wordpress/">How to Speed up Your WordPress Site</a> by Kinsta.
</body>
</html>
