<?php
#####################################################################################################
# SXVLink Config Class
#####################################################################################################

class SVXLink {

    private $settingsArray;
    private $portsArray;
	private $modulesArray;
	private $modulesListArray;
	private $logics = array();
	private $links = array();
	private $orpFileHeader;
    private $web_path = '/var/www/openrepeater/';	

	public function __construct($settingsArray, $portsArray, $modulesArray) {
		$this->settingsArray = $settingsArray;
		$this->portsArray = $portsArray;
		$this->modulesArray = $modulesArray;
		
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
	# Build Module Config Files
	###############################################

	public function build_module_list() {

		$this->write_module_configs();

		// Build Module List from Array
		if(!empty($this->modulesListArray)) {
			return array( 'MODULES' => implode(",", $this->modulesListArray) );
		} else {
			return array( '#MODULES' => 'NONE' );
		}

	}


	public function write_module_configs() {
		$this->modulesListArray = array();
		foreach($this->modulesArray as $cur_mod) { 
			if ($cur_mod['moduleEnabled']==1) {
				$module_config_array = array();
				
				// Add Module name to array to output list in logic section
				$this->modulesListArray[] = 'Module'.$cur_mod['svxlinkName'];
						
				// Build Module Configuration
				$mod_build_file = $this->web_path . 'modules/' . $cur_mod['svxlinkName'] . '/build_config.php';
				if (file_exists($mod_build_file)) {
					// Module has a build file...use it.
					include($mod_build_file);
		
				} else {
					// Module doesn't have a build file so create minimal configuration
					$module_config_array['Module'.$cur_mod['svxlinkName']] = [
						'NAME' => $cur_mod['svxlinkName'],
						'ID' => $cur_mod['svxlinkID'],
						'TIMEOUT' => '60',				
					];
				}
								
				// Write out Module Config File for SVXLink
				$this->write_config($module_config_array, 'Module'.$cur_mod['svxlinkName'].'.conf', 'ini');
		
			} 
		}
	}
	
	
	
	###############################################
	# Build Global Section
	###############################################

	public function build_global() {
		$logicsList = implode(",", $this->logics); // Convert array to CSV.
		
		$global_array['MODULE_PATH'] = '/usr/lib/arm-linux-gnueabihf/svxlink';
		$global_array['LOGICS'] = $logicsList;
		$global_array['CFG_DIR'] = 'svxlink.d';
		$global_array['TIMESTAMP_FORMAT'] = '"%c"';
		$global_array['CARD_SAMPLE_RATE'] = '48000';
		//$global_array['LOCATION_INFO'] = 'LocationInfo';

		// Add Link Sections if defined
		if (!empty($this->links)) {
			$linksList = implode(",", $this->links); // Convert array to CSV.
			$global_array['LINKS'] = $linksList;
		}
		
		return $global_array;
	}
	

	
	###############################################
	# Build RX Port
	###############################################

	public function build_rx($curPort, $curPortType = 'GPIO') {
		$audio_dev = explode("|", $this->portsArray[$curPort]['rxAudioDev']);
		
		$rx_array['RX_Port'.$curPort] = [
			'TYPE' => 'Local',
			'AUDIO_DEV' => $audio_dev[0],
			'AUDIO_CHANNEL' => $audio_dev[1],
		];
	
		if (strtolower($this->portsArray[$curPort]['rxMode']) == 'vox') {
			// VOX Squelch Mode
			$rx_array['RX_Port'.$curPort] += [
				'SQL_DET' => 'VOX',
				'VOX_FILTER_DEPTH' => '150',
				'VOX_THRESH' => '300',
				'SQL_HANGTIME' => '1000',
			];
	
		} else {

			// COS Squelch Mode
			switch ($curPortType) {		
			    case 'GPIO':
					$rx_array['RX_Port'.$curPort] += [
						'SQL_DET' => 'GPIO',
						'GPIO_SQL_PIN' => 'gpio' . $this->portsArray[$curPort]['rxGPIO'],
						'SQL_HANGTIME' => '10',
					];
			        break;
	
			    case 'HiDraw':
					$hidDev = trim( $this->portsArray[$curPort]['hidrawDev'] );
					if ($this->portsArray[$curPort]['hidrawRX_cos_invert'] == true) {
						$hid_pin = '!' . $this->portsArray[$curPort]['hidrawRX_cos']; // Inverted Logic
					} else {
						$hid_pin = $this->portsArray[$curPort]['hidrawRX_cos']; // Normal Logic
					}
					$rx_array['RX_Port'.$curPort] += [
						'SQL_DET' => 'HIDRAW',
						'HID_DEVICE' => $hidDev,
						'HID_SQL_PIN' => $hid_pin,
						'SQL_HANGTIME' => '10',
					];

					// Set Hidraw device permission, This should really be moved to the start of SVXLink. 
					exec("sudo orp_helper hidraw owner $hidDev", $version);
			        break;
			}

		}
	
		$rx_array['RX_Port'.$curPort] += [
			'SQL_START_DELAY' => '1',
			'SQL_DELAY' => '10',
			'SIGLEV_SLOPE' => '1',
			'SIGLEV_OFFSET' => '0',
			'SIGLEV_OPEN_THRESH' => '30',
			'SIGLEV_CLOSE_THRESH' => '10',
			'DEEMPHASIS' => '1',
			'PEAK_METER' => '0',
			'DTMF_DEC_TYPE' => 'INTERNAL',
			'DTMF_MUTING' => '1',
			'DTMF_HANGTIME' => '100',
			'DTMF_SERIAL' => '/dev/ttyS0',
		];
	
		return $rx_array;
	}



	###############################################
	# Build TX Port
	###############################################

	public function build_tx($curPort, $curPortType = 'GPIO') {
		$audio_dev = explode("|", $this->portsArray[$curPort]['txAudioDev']);
	
		$tx_array['TX_Port'.$curPort] = [
			'TYPE' => 'Local',
			'AUDIO_DEV' => $audio_dev[0],
			'AUDIO_CHANNEL' => $audio_dev[1],
			'PTT_HANGTIME' => ($this->settingsArray['txTailValueSec'] * 1000),
			'TIMEOUT' => '300',
			'TX_DELAY' => '50',
		];
	
		switch ($curPortType) {		
		    case 'GPIO':
				$tx_array['TX_Port'.$curPort] += [
					'PTT_TYPE' => 'GPIO',
					'PTT_PORT' => 'GPIO',
					'PTT_PIN' => 'gpio'.$this->portsArray[$curPort]['txGPIO'],
				];
		        break;

		    case 'HiDraw':
				$hidDev = trim( $this->portsArray[$curPort]['hidrawDev'] );
				if ($this->portsArray[$curPort]['hidrawTX_ptt_invert'] == true) {
					$hid_pin = '!' . $this->portsArray[$curPort]['hidrawTX_ptt']; // Inverted Logic
				} else {
					$hid_pin = $this->portsArray[$curPort]['hidrawTX_ptt']; // Normal Logic
				}
				$tx_array['TX_Port'.$curPort] += [
					'PTT_TYPE' => 'Hidraw',
					'HID_DEVICE' => $hidDev,
					'HID_PTT_PIN' => $hid_pin,
				];

				// Set Hidraw device permission, This should really be moved to the start of SVXLink. 
				exec("sudo orp_helper hidraw owner $hidDev", $version);
		        break;
		}

		if ($this->settingsArray['txTone']) {
			$tx_array['TX_Port'.$curPort] += [
				'CTCSS_FQ' => $this->settingsArray['txTone'],
				'CTCSS_LEVEL' => '9',
			];
		}
	
		$tx_array['TX_Port'.$curPort] += [
			'PREEMPHASIS' => '0',
			'DTMF_TONE_LENGTH' => '100',
			'DTMF_TONE_SPACING' => '50',
			'DTMF_TONE_PWR' => '-18',
		];
	
		return $tx_array;
	}



	###############################################
	# Build Full Duplex Logic (Repeater)
	###############################################

	public function build_full_duplex_logic($logicName,$curPort) {
		$this->logics[] = $logicName; // Add this logic to list for Globals Section

		$logic_array[$logicName] = [
			'TYPE' => 'Repeater',
			'RX' => 'RX_Port' . $curPort,
			'TX' => 'TX_Port' . $curPort,
		];

		$logic_array[$logicName] += $this->build_module_list();

		$logic_array[$logicName] += [
			'CALLSIGN' => $this->settingsArray['callSign']
		];

		# Short ID
		if ($this->settingsArray['ID_Short_Mode'] == 'disabled') {
			$logic_array[$logicName] += [
				'#SHORT_IDENT_INTERVAL' => '0'
			];
		} else {
			$logic_array[$logicName] += [
				'SHORT_IDENT_INTERVAL' => $this->settingsArray['ID_Short_IntervalMin']
			];			
		}

		# ID only if there is activity, only affect short IDs
		if ($this->settingsArray['ID_Only_When_Active'] == 'True') {
			$logic_array[$logicName] += [
				'IDENT_ONLY_AFTER_TX' => '4'
			];
		} else {
			$logic_array[$logicName] += [
				'#IDENT_ONLY_AFTER_TX' => '0'
			];			
		}

		#Long ID
		if ($this->settingsArray['ID_Long_Mode'] == 'disabled') {
			$logic_array[$logicName] += [
				'#LONG_IDENT_INTERVAL' => '0'
			];
		} else {
			$logic_array[$logicName] += [
				'LONG_IDENT_INTERVAL' => $this->settingsArray['ID_Long_IntervalMin']
			];			
		}


		$logic_array[$logicName] += [
			'EVENT_HANDLER' => '/usr/share/svxlink/events.tcl',
			'DEFAULT_LANG' => 'en_US',
			'RGR_SOUND_DELAY' => '1',
			'REPORT_CTCSS' => $this->settingsArray['rxTone'],
			'TX_CTCSS' => 'ALWAYS',
			'MACROS' => 'Macros',
			'FX_GAIN_NORMAL' => '0',
			'FX_GAIN_LOW' => '-12',
			'IDLE_TIMEOUT' => '1',
			'OPEN_ON_SQL' => '1',
			'OPEN_SQL_FLANK' => 'OPEN',
			'IDLE_SOUND_INTERVAL' => '0',
		];
		
		if ($this->settingsArray['repeaterDTMF_disable'] == 'True') {
			$logic_array[$logicName] += [
				'ONLINE_CMD' => $this->settingsArray['repeaterDTMF_disable_pin'],
			];
		}
	
	
		return $logic_array;
	}



	###############################################
	# Build Half Duplex Logic (Simplex)
	###############################################

	public function build_half_duplex_logic($logicName,$curPort,$modules=false) {
		$this->logics[] = $logicName; // Add this logic to list for Globals Section

		$logic_array[$logicName] = [
			'TYPE' => 'Simplex',
			'RX' => 'RX_Port' . $curPort,
			'TX' => 'TX_Port' . $curPort,
		];

		if ($modules) {
			$logic_array[$logicName] += $this->build_module_list();
		}

		$logic_array[$logicName] += [
			'CALLSIGN' => $this->settingsArray['callSign']
		];

		# Short ID
		if ($this->settingsArray['ID_Short_Mode'] == 'disabled') {
			$logic_array[$logicName] += [
				'#SHORT_IDENT_INTERVAL' => '0'
			];
		} else {
			$logic_array[$logicName] += [
				'SHORT_IDENT_INTERVAL' => $this->settingsArray['ID_Short_IntervalMin']
			];			
		}

		# ID only if there is activity, only affect short IDs
		if ($this->settingsArray['ID_Only_When_Active'] == 'True') {
			$logic_array[$logicName] += [
				'IDENT_ONLY_AFTER_TX' => '4'
			];
		} else {
			$logic_array[$logicName] += [
				'#IDENT_ONLY_AFTER_TX' => '0'
			];			
		}

		#Long ID
		if ($this->settingsArray['ID_Long_Mode'] == 'disabled') {
			$logic_array[$logicName] += [
				'#LONG_IDENT_INTERVAL' => '0'
			];
		} else {
			$logic_array[$logicName] += [
				'LONG_IDENT_INTERVAL' => $this->settingsArray['ID_Long_IntervalMin']
			];			
		}

		$logic_array[$logicName] += [
			'EVENT_HANDLER' => '/usr/share/svxlink/events.tcl',
			'DEFAULT_LANG' => 'en_US',
			'RGR_SOUND_DELAY' => '1',
			'REPORT_CTCSS' => $this->settingsArray['rxTone'],
			'TX_CTCSS' => 'ALWAYS',
			'MACROS' => 'Macros',
			'FX_GAIN_NORMAL' => '0',
			'FX_GAIN_LOW' => '-12',
			'IDLE_TIMEOUT' => '1',
			'OPEN_ON_SQL' => '1',
			'OPEN_SQL_FLANK' => 'OPEN',
			'IDLE_SOUND_INTERVAL' => '0',
		];
		
		/*
		if ($this->settingsArray['repeaterDTMF_disable'] == 'True') {
			$logic_array[$logicName] += [
				'ONLINE_CMD' => $this->settingsArray['repeaterDTMF_disable_pin'],
			];
		}
		*/	
	
		return $logic_array;
	}


	###############################################
	# Build LINK Section
	###############################################

	public function build_link($linkGroupNum, $logicsArray, $linkActive = true) {
		$linkName = 'LinkGroup' . $linkGroupNum;
		$this->links[] = $linkName; // Add this link section to link list for declaration in Globals Section

		foreach($logicsArray as $currLogicKey => $currLogicName) {
			$currLinkString = $currLogicName;
			$currLinkString .= ':9' . $linkGroupNum;
			$currLinkString .= ':' . $this->settingsArray['callSign'];
			$outputLogicArray[$currLogicKey] =  $currLinkString;
			echo $currLogicName . '<br>';
		}

		$link_array[$linkName] = [
			'CONNECT_LOGICS' => implode(",", $outputLogicArray),			
		];

		if ($linkActive == true) {
			$link_array[$linkName] += [
				'DEFAULT_ACTIVE' => '1',
			];
		}

		$link_array[$linkName] += [
			'#TIMEOUT' => '300', // In seconds				
		];

		return $link_array;
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
				$ini_return .= $key . "=" . $value . "\n";
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
	# Delete ALL Custom Events
	###############################################

	public function delete_custom_evnets() {
		$files = glob('/usr/share/svxlink/events.d/' . 'ORP_*');
		array_map('unlink', $files);
	}


}
?>