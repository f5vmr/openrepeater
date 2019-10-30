<?php
#####################################################################################################
# Functions Class
#####################################################################################################

class Functions {

	private $orpFileHeader;


	public function __construct() {
		$orpFileHeader = '
		###############################################################################
		#
		#  OPENREPEATER / SVXLINK CONFIGURATION FILE
		#  This file was auto generated by OpenRepeater.
		#  DO NOT MAKE CHANGES IN THIS FILE AS THEY WILL BE OVERWRITTEN
		#
		###############################################################################';
		#Clean up tabs/white spaces
		$this->orpFileHeader = trim(preg_replace('/\t+/', '', $orpFileHeader)) . "\n\n";
	}



	###############################################
	# Build INI Format
	###############################################

	public function build_ini($input_array) {
		$section_separator = '###############################################################################';

		$ini_return = "";
		$section_count = 0;
		foreach($input_array as $ini_section => $ini_section_array) {
			$section_count++;
			if ($section_count > 1) { $ini_return .= $section_separator . "\n\n";}
			$ini_return .= "[" . $ini_section . "]\n";
			foreach($ini_section_array as $key => $value) {
				$value = trim($value);
				if ($value != '') {
					$ini_return .= $key . "=" . $value . "\n";
				}
			}
			$ini_return .= "\n";
		}

		return $ini_return;
	}



	###############################################
	# Write Config File
	###############################################

	public function write_config($data, $filename, $format = 'text') {

		if ($format == 'ini') {
			$data = $this->build_ini($data); // Convert Array to INI format
		}
		$file_output = $this->orpFileHeader . $data;

		switch ($filename) {
		case "svxlink.conf":
			$filepath = '/etc/svxlink/';
			break;
		case "gpio.conf":
			$filepath = '/etc/svxlink/';
			break;
		case "devices.conf":
			$filepath = '/etc/svxlink/';
			break;
		case "Logic.tcl":
			$filepath = '/usr/share/svxlink/events.d/local/';
			break;
		case (strpos($filename, "Module") === 0): // Begins with Module
			$filepath = '/etc/svxlink/svxlink.d/';
			break;
		case (strpos($filename, "ORP_") === 0): // Event beginning with ORP_
			$filepath = '/usr/share/svxlink/events.d/';
			break;
		}

		$full_file_path = $filepath . $filename;

		// If Directory Doesn't Exist, create it.
		if (!is_dir($filepath)) { mkdir($filepath); }

		// Write the File.
		file_put_contents( $full_file_path, $file_output );
	}



	###############################################
	# Geo Functions
	###############################################

	public function geo_convert($latitude, $longitude, $format=null) {
		$latitudeDirection = $latitude < 0 ? 'S': 'N';
		$longitudeDirection = $longitude < 0 ? 'W': 'E';

		$latitudeNotation = $latitude < 0 ? '-': '';
		$longitudeNotation = $longitude < 0 ? '-': '';

		$latitudeInDegrees = floor(abs($latitude));
		$longitudeInDegrees = floor(abs($longitude));

		$latitudeDecimal = abs($latitude)-$latitudeInDegrees;
		$longitudeDecimal = abs($longitude)-$longitudeInDegrees;

		$latParts = explode(".",$latitude);
		$latTempma = "0.".$latParts[1];
		$latTempma = $latTempma * 3600;
		$latitudeMinutes = floor($latTempma / 60);
		$latitudeSeconds = $latTempma - ($latitudeMinutes*60);

		$longParts = explode(".",$longitude);
		$longTempma = "0.".$longParts[1];
		$longTempma = $longTempma * 3600;
		$longitudeMinutes = floor($longTempma / 60);
		$longitudeSeconds = $longTempma - ($longitudeMinutes*60);

		switch ($format) {
		case 'svxlink':
			$precision = 0;
			$latitudeSeconds = round($latitudeSeconds,$precision);
			$longitudeSeconds = round($longitudeSeconds,$precision);
			$outputFormat = '%s.%s.%s%s'; // SVXLink Format
			break;
		default:
			$precision = 1;
			$latitudeSeconds = round($latitudeSeconds,$precision);
			$longitudeSeconds = round($longitudeSeconds,$precision);
			$outputFormat = '%s°%s\'%s"%s'; // Google DMS
		}

		return [
		'latitude' => sprintf($outputFormat,$latitudeInDegrees,$latitudeMinutes,$latitudeSeconds,$latitudeDirection),
		'longitude' => sprintf($outputFormat,$longitudeInDegrees,$longitudeMinutes,$longitudeSeconds,$longitudeDirection)
		];
	}


}