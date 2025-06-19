<?php
namespace AutoAltPro\Log;

class AutoAlt_Pro_Log_Manager {
    const MAX_FILE_SIZE = 5242880; // 5 MB
    protected static $initialized = false;
    protected static $log_directory;
    protected static $log_file;

    public static function init() {
        if ( self::$initialized ) {
            return;
        }
        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['basedir'] ) ) {
            error_log( 'AutoAlt Pro: Failed to get upload directory.' );
            return;
        }
        self::$log_directory = trailingslashit( $upload_dir['basedir'] ) . 'autoalt-pro/logs/';
        self::$log_file = self::$log_directory . 'plugin.log';

        if ( ! file_exists( self::$log_directory ) ) {
            if ( ! wp_mkdir_p( self::$log_directory ) && ! file_exists( self::$log_directory ) ) {
                error_log( 'AutoAlt Pro: Failed to create log directory: ' . self::$log_directory );
            }
        }

        if ( ! file_exists( self::$log_file ) ) {
            $fp = fopen( self::$log_file, 'c+' );
            if ( $fp ) {
                fclose( $fp );
            } else {
                error_log( 'AutoAlt Pro: Failed to create log file: ' . self::$log_file );
            }
        }
        self::$initialized = true;
    }

    public static function log( $level, $message, $context = array() ) {
        self::init();
        if ( empty( self::$log_file ) ) {
            return;
        }
        $levels = array( 'debug', 'info', 'warning', 'error' );
        if ( ! in_array( $level, $levels, true ) ) {
            $level = 'info';
        }
        $entry = array(
            'timestamp' => date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        );
        $line = wp_json_encode( $entry ) . PHP_EOL;

        $fp = fopen( self::$log_file, 'c+' );
        if ( false === $fp ) {
            error_log( 'AutoAlt Pro: Failed to open log file for writing: ' . self::$log_file );
            return;
        }
        if ( ! flock( $fp, LOCK_EX ) ) {
            error_log( 'AutoAlt Pro: Could not lock log file: ' . self::$log_file );
            fclose( $fp );
            return;
        }
        clearstatcache( true, self::$log_file );
        $stat = fstat( $fp );
        if ( isset( $stat['size'] ) && $stat['size'] >= self::MAX_FILE_SIZE ) {
            fflush( $fp );
            flock( $fp, LOCK_UN );
            fclose( $fp );
            $timestamp = date_i18n( 'Y-m-d_H-i-s', current_time( 'timestamp' ) );
            $archive = self::$log_directory . 'plugin-' . $timestamp . '.log';
            if ( ! rename( self::$log_file, $archive ) ) {
                error_log( 'AutoAlt Pro: Failed to rotate log file: ' . self::$log_file );
            }
            $fp = fopen( self::$log_file, 'c+' );
            if ( false === $fp ) {
                error_log( 'AutoAlt Pro: Failed to create new log file after rotation: ' . self::$log_file );
                return;
            }
            if ( ! flock( $fp, LOCK_EX ) ) {
                error_log( 'AutoAlt Pro: Could not lock new log file after rotation: ' . self::$log_file );
                fclose( $fp );
                return;
            }
        }
        fseek( $fp, 0, SEEK_END );
        fwrite( $fp, $line );
        fflush( $fp );
        flock( $fp, LOCK_UN );
        fclose( $fp );
    }

    public static function debug( $message, $context = array() ) {
        self::log( 'debug', $message, $context );
    }

    public static function info( $message, $context = array() ) {
        self::log( 'info', $message, $context );
    }

    public static function warning( $message, $context = array() ) {
        self::log( 'warning', $message, $context );
    }

    public static function error( $message, $context = array() ) {
        self::log( 'error', $message, $context );
    }

    public static function get_logs( $limit = 100, $reverse = false ) {
        self::init();
        if ( ! file_exists( self::$log_file ) ) {
            return array();
        }
        $logs = array();
        $fp = fopen( self::$log_file, 'r' );
        if ( false === $fp ) {
            error_log( 'AutoAlt Pro: Failed to open log file for reading: ' . self::$log_file );
            return $logs;
        }
        fseek( $fp, 0, SEEK_END );
        $file_size = ftell( $fp );
        $buffer = '';
        $chunk_size = 4096;
        $pos = -1;
        $line_count = 0;
        while ( $file_size + $pos >= 0 && $line_count <= $limit ) {
            $seek = max( $file_size + $pos - $chunk_size + 1, 0 );
            $read_length = $file_size + $pos - $seek + 1;
            fseek( $fp, $seek );
            $data = fread( $fp, $read_length );
            if ( false === $data ) {
                break;
            }
            $buffer = $data . $buffer;
            $line_count += substr_count( $data, "\n" );
            $pos -= $chunk_size;
        }
        fclose( $fp );
        $all_lines = explode( "\n", trim( $buffer ) );
        if ( count( $all_lines ) > $limit ) {
            $all_lines = array_slice( $all_lines, -$limit );
        }
        if ( $reverse ) {
            $all_lines = array_reverse( $all_lines );
        }
        foreach ( $all_lines as $line ) {
            $decoded = json_decode( $line, true );
            if ( $decoded ) {
                $logs[] = $decoded;
            }
        }
        return $logs;
    }

    public static function clear_logs() {
        self::init();
        if ( file_exists( self::$log_file ) ) {
            if ( ! unlink( self::$log_file ) ) {
                error_log( 'AutoAlt Pro: Failed to delete log file: ' . self::$log_file );
            }
            $fp = fopen( self::$log_file, 'c+' );
            if ( $fp ) {
                fclose( $fp );
            } else {
                error_log( 'AutoAlt Pro: Failed to recreate log file after clearing: ' . self::$log_file );
            }
        }
    }
}

AutoAlt_Pro_Log_Manager::init();