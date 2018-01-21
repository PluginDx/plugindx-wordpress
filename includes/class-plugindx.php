<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'wp_kses_allowed_html', 'allow_html_data_attributes', 10, 2 );
add_action( 'wp_ajax_plugindx_report', 'plugindx_report' );

// Allow HTML data attributes to be used for PluginDx embed in WooCommerce forms
function allow_html_data_attributes( $allowed, $context ) {
	if ( current_user_can( 'administrator' ) ) {
		if ( 'post' == $context ) {
			$allowed['div']['data-auto-init'] = true;
			$allowed['div']['data-key'] = true;
			$allowed['div']['data-report'] = true;
			$allowed['div']['data-label'] = true;
			$allowed['div']['data-platform'] = true;
			$allowed['div']['data-email'] = true;
			$allowed['div']['data-type'] = true;
			$allowed['div']['data-color'] = true;
			$allowed['div']['data-overlay-icon'] = true;
			$allowed['div']['data-overlay-placement'] = true;
			$allowed['div']['data-target'] = true;
			$allowed['div']['data-translations'] = true;
			$allowed['div']['data-on-ready'] = true;
			$allowed['div']['data-message-fields'] = true;
			$allowed['div']['*'] = true;
		}
	}

	return $allowed;
}

function plugindx_report() {
	if ( current_user_can( 'administrator' ) ) {
		$config = json_decode( file_get_contents( 'php://input' ), true );

		if ( $config ) {
			try {
				$report = new PluginDx_Report( $config );
				wp_send_json( $report->get() );
			} catch ( Exception $e ) {
				wp_send_json_error( 'Unable to build report: ' . $e->getMessage() );
			}
		} else {
			wp_send_json_error( 'Invalid config data' );
		}
	}

	wp_die();
}

class PluginDx_Report {
	private $report;

	public function __construct( $config ) {
        if ( is_array( $config ) ) {
            $this->report = $config;
        } else {
            $this->report = json_decode( $config, true );
        }

		$this->get_config();
        $this->get_collections();
        $this->get_helpers();
		$this->get_server_info();
		$this->get_logs();
		$this->get_extra();
	}

	public function get() {
		return wp_json_encode( $this->report );
	}

    private function get_config() {
        if ( ! isset( $this->report['config'] ) ) {
            return;
        }

        $configFields = $this->report['config'];

        foreach ( $configFields as $fieldIndex => $field ) {
			$this->report['config'][ $fieldIndex ]['value'] = get_option( $field['path'] );
        }
	}

	private function get_collections() {
        if ( ! isset( $this->report['collections'] ) ) {
            return;
        }

		global $wpdb;
        $collections = $this->report['collections'];

        foreach ( $collections as $collectionIndex => $collection ) {
			$collectionQuery = 'SELECT ';

            if ( isset( $collection['count'] ) ) {
                $collectionQuery .= 'COUNT(*)';
            } elseif ( isset( $collection['attributes'] ) ) {
				$collectionQuery .= implode( ', ', $collection['attributes'] );
            }

			$collectionQuery .= ' FROM ' . $wpdb->prefix . $collection['model'];

			$this->report['collections'][ $collectionIndex ]['data'] = $wpdb->get_results( $wpdb->prepare( $collectionQuery ) );
        }
	}

	private function get_helpers() {
        if ( ! isset( $this->report['helpers'] ) ) {
            return;
        }

		$helpers = $this->report['helpers'];
		$theme_data = $this->get_theme_data();

        foreach ( $helpers as $helperIndex => $helper ) {
            $helperData = '';

            switch ( $helper['path'] ) {
                case 'wordpress/version':
                    $helperData = get_bloginfo( 'version' );
                    break;
				case 'woocommerce/version':
					global $woocommerce;
					$helperData = $woocommerce->version;
					break;
				case 'woocommerce/database_version':
					$helperData = get_option( 'woocommerce_db_version' );
					break;
                case 'wordpress/plugins':
					$helperData = get_plugins();
                    break;
				case 'wordpress/plugin_data':
					$helperData = $this->get_plugin_data( $this->report['module'] );
					break;
                case 'wordpress/plugin_version':
					$helperData = $this->get_plugin_data( $this->report['module'] )['Version'];
					break;
				case 'wordpress/multisite':
					$helperData = is_multisite();
					break;
				case 'wordpress/memory_limit':
					$helperData = $this->let_to_num( WP_MEMORY_LIMIT );
					if ( function_exists( 'memory_get_usage' ) ) {
						$helperData = max( $helperData, $this->let_to_num( @ini_get( 'memory_limit' ) ) );
					}
					break;
				case 'wordpress/debug_mode':
					$helperData = ( defined( 'WP_DEBUG' ) && WP_DEBUG );
					break;
				case 'wordpress/cron':
					$helperData = ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
					break;
				case 'wordpress/language':
					$helperData = get_locale();
					break;
				case 'wordpress/theme_name':
					$helperData = $theme_data['name'];
					break;
				case 'wordpress/theme_version':
					$helperData = $theme_data['version'];
					break;
				case 'wordpress/theme_author_url':
					$helperData = $theme_data['author_url'];
					break;
				case 'wordpress/child_theme':
					$helperData = $theme_data['is_child_theme'];
					break;
				case 'wordpress/parent_theme_name':
					$helperData = $theme_data['parent_name'];
					break;
				case 'wordpress/parent_theme_version':
					$helperData = $theme_data['parent_version'];
					break;
				case 'wordpress/parent_theme_author_url':
					$helperData = $theme_data['parent_author_url'];
					break;
            }

            $this->report['helpers'][ $helperIndex ]['value'] = $helperData;
        }
	}

	private function get_plugin_data( $pluginFile ) {
		$plugins = get_plugins();
		$currentPlugin = preg_grep( '/.*' . $pluginFile . '/', array_keys( $plugins ) );
		$currentPluginFile = array_shift( array_values( $currentPlugin ) );

		if ( isset( $currentPluginFile ) ) {
			return $plugins[ $currentPluginFile ];
		}
	}

	private function get_theme_data() {
		$active_theme = wp_get_theme();
		$parent_theme_info = array(
			'parent_name' => '',
			'parent_version' => '',
			'parent_author_url' => '',
		);

		if ( is_child_theme() ) {
			$parent_theme = wp_get_theme( $active_theme->Template );
			$parent_theme_info = array(
				'parent_name' => $parent_theme->Name,
				'parent_version' => $parent_theme->Version,
				'parent_author_url' => $parent_theme->{'Author URI'},
			);
		}

		$active_theme_info = array(
			'name' => $active_theme->Name,
			'version' => $active_theme->Version,
			'author_url' => esc_url_raw( $active_theme->{'Author URI'} ),
			'is_child_theme' => is_child_theme(),
		);

		return array_merge( $active_theme_info, $parent_theme_info );
	}

	private function get_server_info() {
        if ( ! isset( $this->report['server'] ) ) {
            return;
        }

        $serverFields = $this->report['server'];
        $serverInfo = $this->parse_server_info();

        foreach ( $serverFields as $fieldIndex => $field ) {
            $fieldKeys = explode( '/', $field['path'] );
            $fieldValue = $serverInfo;

            foreach ( $fieldKeys as $fieldKey ) {
                if ( isset( $fieldValue[ $fieldKey ] ) ) {
                    $fieldValue = $fieldValue[ $fieldKey ];
                }
            }

            $this->report['server'][ $fieldIndex ]['value'] = $fieldValue;
        }
	}

	private function parse_server_info() {
        ob_start();
		phpinfo( INFO_MODULES );
		$s = ob_get_contents();
		ob_end_clean();

        $s = strip_tags( $s, '<h2><th><td>' );
        $s = preg_replace( '/<th[^>]*>([^<]+)<\/th>/', '<info>\1</info>', $s );
        $s = preg_replace( '/<td[^>]*>([^<]+)<\/td>/', '<info>\1</info>', $s );
        $t = preg_split( '/(<h2[^>]*>[^<]+<\/h2>)/', $s, -1, PREG_SPLIT_DELIM_CAPTURE );
        $r = array();
		$count = count( $t );
        $p1 = '<info>([^<]+)<\/info>';
        $p2 = '/' . $p1 . '\s*' . $p1 . '\s*' . $p1 . '/';
        $p3 = '/' . $p1 . '\s*' . $p1 . '/';

        for ( $i = 1; $i < $count; $i++ ) {
            if ( preg_match( '/<h2[^>]*>([^<]+)<\/h2>/', $t[ $i ], $matchs ) ) {
                $name = trim( $matchs[1] );
                $vals = explode( "\n", $t[ $i + 1 ] );
                foreach ( $vals AS $val ) {
                    if ( preg_match( $p2, $val, $matchs ) ) {
                        $r[ $name ][ trim( $matchs[1] ) ] = array( trim( $matchs[2] ), trim( $matchs[3] ) );
                    } elseif ( preg_match( $p3, $val, $matchs ) ) {
                        $r[ $name ][ trim( $matchs[1] ) ] = trim( $matchs[2] );
                    }
                }
            }
        }

        return $r;
	}

	private function get_logs() {
        if ( ! isset( $this->report['logs'] ) ) {
            return;
		}

		$logs = $this->report['logs'];

		foreach ( $logs as $logIndex => $log ) {
			if ( isset( $log['path'] ) ) {
				$logResults = array();

				if ( defined( 'WC_LOG_DIR' ) ) {
					foreach ( @glob( trailingslashit( WC_LOG_DIR ) . $log['path'] ) as $filename ) {
						$logResults[] = $filename;
					}
				}

				foreach ( @glob( trailingslashit( wp_upload_dir()['basedir'] ) . $log['path'] ) as $filename ) {
					$logResults[] = $filename;
				}

				foreach ( @glob( trailingslashit( WP_CONTENT_DIR ) . $log['path'] ) as $filename ) {
					$logResults[] = $filename;
				}

				if ( isset( $logResults[0] ) ) {
					$this->report['logs'][ $logIndex ]['value'] = $this->tail_file( $logResults[0], $log['lines'] );
				}
			}
		}
	}

	private function get_extra() {
		if ( isset( $this->report['integration_id'] ) ) {
            try {
				$extraData = apply_filters( 'plugindx_framework_report_' . $this->report['integration_id'], '' );
				$this->report['extra'] = $extraData;
            } catch ( Exception $e ) {
                $this->report['extra'] = array(
                    'error' => array(
                        'message' => $e->getMessage(),
					),
				);
            }
		}
	}

	private function let_to_num( $size ) {
		$l = substr( $size, -1 );
		$ret = substr( $size, 0, -1 );

		switch ( strtoupper( $l ) ) {
			case 'P':
				$ret *= 1024;
			case 'T':
				$ret *= 1024;
			case 'G':
				$ret *= 1024;
			case 'M':
				$ret *= 1024;
			case 'K':
				$ret *= 1024;
		}

		return $ret;
	}

	/**
	 * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
	 * @author Torleif Berger, Lorenzo Stanco
	 * @link http://stackoverflow.com/a/15025877/995958
	 * @license http://creativecommons.org/licenses/by/3.0/
	 */
	private function tail_file( $filepath, $lines = 100, $adaptive = true ) {
		$f = @fopen( $filepath, "rb" );
		if ( false === $f ) {
			return false;
		}
		if ( ! $adaptive ) {
			$buffer = 4096;
		} else {
			$buffer = ( $lines < 2 ? 64 : ( $lines < 10 ? 512 : 4096 ) );
		}
		fseek( $f, -1, SEEK_END );
		if ( "\n" != fread( $f, 1 ) ) {
			$lines -= 1;
		}
		$output = '';
		$chunk = '';
		while ( ftell( $f ) > 0 && $lines >= 0 ) {
			$seek = min( ftell( $f ), $buffer );
			fseek( $f, -$seek, SEEK_CUR );
			$output = ( $chunk = fread( $f, $seek ) ) . $output;
			fseek( $f, -mb_strlen( $chunk, '8bit' ), SEEK_CUR );
			$lines -= substr_count( $chunk, "\n" );
		}
		while ( $lines++ < 0 ) {
			$output = substr( $output, strpos( $output, "\n" ) + 1 );
		}
		fclose( $f );
		return trim( $output );
	}
}
