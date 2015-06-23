<pre><?php

require_once('../vendor/autoload.php');
require_once('../common.inc.php');

class CanvasAPIviaLTI_Installer {
	const SECRETS_NEEDED_STEP = 0;
	const SECRETS_ENTERED_STEP = 1;
	const API_DECISION_NEEDED_STEP = 2;
	const API_DECISION_ENTERED_STEP = 3;
	
	/**
	 * Append another message to the output of the install script.
	 *
	 * TODO maybe some nicer HTML generation
	 *
	 * @param string $message The message to append (HTML formatting is fine).
	 **/
	public static function appendMessage($message) {
		echo "$message\n";
	}
	
	/**
	 * Generate a SECRETS_FILE from user input.
	 * @throws CanvasAPIviaLTI_Installer_Exception If form submission does not contain all required MySQL credentals (host, username, password and database)
	 * @throws CanvasAPIviaLTI_Installer_Exception If SECRETS_FILE cannot be created
	 * @throws CanvasAPIviaLTI_Installer_Exception If $step is not a pre-defined *_STEP constant
	 **/
	public static function createSecretsFile($step = self::SECRETS_NEEDED_STEP) {
		switch ($step) {
			case self::SECRETS_NEEDED_STEP: {
				// FIXME passwords in clear text? oy.
				echo '
					<html>
					<body>
					<form action="' . $_SERVER['PHP_SELF'] . '" method="post">
						<label for="name">App Name <input type="text" name="name" id="name" value="Canvas API via LTI starter" /></label>
						<label for="id">App ID <input type="text" name="id" id="id" value="canvas-lti-via-api-starter" /></label>
						<label for="host">Host <input type="text" name="host" id="host" value="localhost" /></label>
						<label for="username">Username <input type="text" name="username" id="username" /></label>
						<label for="password">Password <input type="password" name="password" id="password" /></label>
						<label for="database">Database <input type="text" name="database" id="database" /></label>
						<label for="oauth_id">OAuth Client ID <input type="text" name="oauth_id" id="oauth_id" /></label>
						<label for="oauth_key">OAuth Client Key <input type="text" name="oauth_key" id="oauth_key" /></label>
						<input type="hidden" name="step" value="' . self::SECRETS_ENTERED_STEP . '" />
						<input type="submit" value="Create Secrets File" />
					</form>
					</body>
					</html>
				';
				exit;
			}
			
			case self::SECRETS_ENTERED_STEP: {
				if (isset($_REQUEST['name']) && isset($_REQUEST['id'])) {
					if (isset($_REQUEST['host']) && isset($_REQUEST['username']) && isset($_REQUEST['password']) && isset($_REQUEST['database'])) {
						$secrets = new SimpleXMLElement('<secrets />');
						$app = $secrets->addChild('app');
						$app->addChild('name', $_REQUEST['name']);
						$app->addChild('id', $_REQUEST['id']);
						$mysql = $secrets->addChild('mysql');
						$mysql->addChild('host', $_REQUEST['host']);
						$mysql->addChild('username', $_REQUEST['username']);
						$mysql->addChild('password', $_REQUEST['password']);
						$mysql->addChild('database', $_REQUEST['database']);
						$oauth = $secrets->addChild('oauth');
						if ($secrets->asXML(SECRETS_FILE) == false) {
							throw new CanvasAPIviaLTI_Exception(
								'Failed to create ' . SECRETS_FILE,
								CanvasAPIviaLTI_Installer_Exception::SECRETS_FILE_CREATION
							);
						}
					} else {
						throw new CanvasAPIviaLTI_Installer_Exception(
							'Missing a required mysql credential (host, username, password and database all required).',
							CanvasAPIviaLTI_Installer_Exception::SECRETS_FILE_MYSQL
						);
					}
				self::appendMessage('Secrets file created.');
				} else {
					throw new CanvasAPIviaLTI_Installer_Exception(
						'Missing a required app identity (name and id both required).',
						CanvasAPIviaLTI_Installer_Exception::SECRETS_FILE_APP
					);
				}
				break;
			}
			
			default: {
				throw new CanvasAPIviaLTI_Installer_Exception(
					"Unknown step ($step) in SECRETS_FILE creation.",
					CanvasAPIviaLTI_Installer_Exception::SECRETS_NEEDED_STEP
				);
			}
		}
	}
	
	/**
	 * Create database tables to back LTI_Tool_Provider
	 * @throws CanvasAPIviaLTI_Installer_Exception If database schema not found in vendors directory
	 * @throws CanvasAPIviaLTI_Installer_Exception If database tables are not created
	 **/
	public static function createLTIDatabaseTables() {
		global $sql;
		
		$ltiSchema = realpath(__DIR__ . '/../vendor/spvsoftwareproducts/LTI_Tool_Provider/lti-tables-mysql.sql');
		
		if ($sql->query("SHOW TABLES LIKE 'lti_%'")->num_rows >= 5) {
			self::appendMessage('LTI database tables already exist');
		} elseif (file_exists($ltiSchema)) {
			$queries = explode(";", file_get_contents($ltiSchema));
			$created = true;
			foreach($queries as $query) {
				if (!empty(trim($query))) {
					if (!$sql->query($query)) {
						throw new CanvasAPIviaLTI_Installer_Exception(
							"Error creating LTI database tables: {$sql->error}",
							CanvasAPIviaLTI_Installer_Exception::LTI_PREPARE_DATABASE
						);
					}
				}
			}
			
			self::appendMessage('LTI database tables created.');
		} else {
			throw new CanvasAPIviaLTI_Exception("$ltiSchema not found.");
		}
	}
	
	/**
	 * Create database tables to back app
	 * @throws CanvasAPIviaLTI_Installer_Exception If database tables are not created
	 **/
	public static function createAppDatabaseTables() {
		global $sql;
		
		if (file_exists(SCHEMA_FILE)) {
			$queries = explode(";", file_get_contents(SCHEMA_FILE));
			$created = true;
			foreach ($queries as $query) {
				if (!empty(trim($query))) {
					if (preg_match('/CREATE\s+TABLE\s+(`([^`]+)`|\w+)/i', $query, $tableName)) {
						$tableName = (empty($tableName[2]) ? $tableName[1] : $tableName[2]);
						if ($sql->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0) {
							$created = false;
						} else {
							if (!$sql->query($query)) {
								throw new CanvasAPIviaLTI_Installer_Exception(
									"Error creating app database tables: {$sql->error}",
									CanvasAPIviaLTI_Installer_Exception::APP_CREATE_TABLE
								);
							}
						}
					} else {
						if (!$sql->query($query)) {
							throw new CanvasAPIviaLTI_Installer_Exception(
								"Error creating app database tables: {$sql->error}",
								CanvasAPIviaLTI_Installer_Exception::APP_PREPARE_DATABASE
							);
						}
					}
				}
			}
			
			if ($created) {
				self::appendMessage('App database tables created.');
			} else {
				self::appendMessage('App database tables already exist.');
			}
		}
	}
	
	/**
	 * Initialize the app metadata store, especially the APP_PATH and APP_URL
	 *
	 * @return AppMetadata
	 **/
	public static function initAppMetadata() {
		global $secrets;
		global $sql;
		global $metadata;
		
		if (AppMetadata::prepareDatabase($sql)) {
			self::appendMessage('App metadata database tables created.');
		} else {
			self::appendMessage('App metadata database tables already exist.');
		}
		
		$metadata = new AppMetadata($sql, (string) $secrets->app->id);
		$metadata['APP_PATH'] = preg_replace('/\/admin$/', '', __DIR__);
		$metadata['APP_URL'] = 'https://' . $_SERVER['SERVER_NAME'] . preg_replace("|^{$_SERVER['DOCUMENT_ROOT']}(.*)$|", '$1', $metadata['APP_PATH']);
	
		self::appendMessage('App metadata initialized.');
		
		return $metadata;
	}
	
	/**
	 * Obtain a Canvas API token, if needed.
	 * @throws CanvasAPIviaLTI_Installer_Exception If $step is not a pre-defined *_STEP constant
	 **/
	public static function acquireAPIToken($step = self::API_DECISION_NEEDED_STEP, $skip = false) {
		global $secrets;
		global $metadata;
		
		if ($skip) {
			if (isset($metadata['CANVAS_API_TOKEN']) || isset($metadata['CANVAS_API_USER'])) {
				$api = new CanvasPest("{$metadata['CANVAS_INSTANCE_URL']}/login/oauth2", $metadata['CANVAS_API_TOKEN']);
				$api->delete('token');
				unset($metadata['CANVAS_API_TOKEN']);
				unset($metadata['CANVAS_API_USER']);
				self::appendMessage('Existing admin Canvas API token information expunged.');
			} else {
				self::appendMessage('No admin Canvas API token acquired.');
			}
		} else {
			switch ($step) {
				case self::API_DECISION_NEEDED_STEP: {
					echo '
						<html>
						<body>
						<form action="' . $metadata['APP_URL'] . '/admin/oauth.php" method="post">
							<label for="url"> Canvas Instance URL <input type="text" name="url" id="url" placeholder="' . $metadata['CANVAS_INSTANCE_URL_PLACEHOLDER'] . '" value="' . $metadata['CANVAS_INSTANCE_URL'] . '" /></label>
							<input type="hidden" name="skip" value="0" />
							<input type="hidden" name="step" value="' . self::API_DECISION_ENTERED_STEP . '" />
							<input type="submit" value="Request administrative token" />
						</form>
						or
						<form action="' . $_SERVER['PHP_SELF'] . '" method="post">
							<input type="hidden" name="skip" value="1" />
							<input type="hidden" name="step" value="' . self::API_DECISION_ENTERED_STEP . '" />
							<input type="submit" value="Require users to acquire individual tokens" />
						</form>
						</body>
						</html>
					';
					exit;
				}
				case self::API_DECISION_ENTERED_STEP: {
					$oauth = new OAuthNegotiator();
					
					if ($oauth->isAPIToken()) {
						$metadata['CANVAS_API_TOKEN'] = $oauth->getToken();
						$metadata['CANVAS_API_USER'] = $oauth->getUser();
						
						self::appendMessage('Admin Canvas API token acquired.');
					}
					break;
				} 
				default: {
					throw new CanvasAPIviaLTI_Installer_Exception(
						"Unknown step ($step) in obtaining API token.",
						CanvasAPIviaLTI_Installer_Exception::API_STEP_MISMATCH
					);
				}
			}
		}
	}
}

class CanvasAPIviaLTI_Installer_Exception extends CanvasAPIviaLTI_Exception {
	const SECRETS_FILE_CREATION = 1;
	const SECRETS_FILE_APP = 2;
	const SECRETS_FILE_MYSQL = 3;
	const LTI_SCHEMA = 4;
	const LTI_PREPARE_DATABASE = 5;
	const LTI_CREATE_TABLE = 6;
	const APP_SCHEMA = 7;
	const APP_PREPARE_DATABASE = 8;
	const APP_CREATE_TABLE = 9;
	const API_STEP_MISMATCH = 10;
	const API_TOKEN = 11;
}

/* test if we already have a working install... */
if ($ready && (!isset($_REQUEST['step']))) {
	CanvasAPIviaLTI_Installer::appendMessage('App already installed.');
	
/* ...otherwise, let's start with the SECRETS_FILE */
} else {
	if(!file_exists(SECRETS_FILE)) {
		if (isset($_REQUEST['step']) && $_REQUEST['step'] == CanvasAPIviaLTI_Installer::SECRETS_ENTERED_STEP) {
			CanvasAPIviaLTI_Installer::createSecretsFile(CanvasAPIviaLTI_Installer::SECRETS_ENTERED_STEP);
		} else {
			CanvasAPIviaLTI_Installer::createSecretsFile();
		}
	}
}

/* establish our database connection */
$secrets = initSecrets();
$sql = initMySql();

try {	
	if (!isset($_REQUEST['step'])) {
		/* load all of our various schema into the database... */
		CanvasAPIviaLTI_Installer::createLTIDatabaseTables();
		CanvasAPIviaLTI_Installer::createAppDatabaseTables();
		
		/* ...and initialize the app metadata... */
		$metadata = CanvasAPIviaLTI_Installer::initAppMetadata();

		/* ...optionally, acquire an API token for the app */
		CanvasAPIviaLTI_Installer::acquireAPIToken(CanvasAPIviaLTI_Installer::API_DECISION_NEEDED_STEP);
	} else {
		$metadata = new AppMetadata($sql, $secrets->app->id);
		$skip = (isset($_REQUEST['skip']) ? $_REQUEST['skip'] : false);
		CanvasAPIviaLTI_Installer::acquireAPIToken($_REQUEST['step'], $skip);
	}
} catch (CanvasAPIviaLTI_Installer_Exception $e) {
	CanvasAPIviaLTI_Installer::appendMessage($e->getMessage() . ' [Error ' . $e->getCode() . ']');
	exit;
}
CanvasAPIviaLTI_Installer::appendMessage('Installation complete.');
	
?></pre>