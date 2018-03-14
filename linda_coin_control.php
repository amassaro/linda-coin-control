<?PHP
require './vendor/autoload.php';

use Noodlehaus\Config;
use PHPMailer\PHPMailer;




$conf = Config::load('./config.json');

$linda_path = $conf->get('linda_path', '/usr/local/bin/Lindad') ?? '/usr/local/bin/Lindad';

if (empty($linda_path) || !file_exists($linda_path)) {
	echo 'Please make sure your config file specifies a valid Lindad path' . PHP_EOL;
	exit;
}

// Database Username, Password & Name
$db_host = $conf->get('db.host', 'localhost') ?? 'localhost';
$db_user = $conf->get('db.user', 'root') ?? 'root';
$db_pass = $conf->get('db.pass');
$db_name = $conf->get('db.database', 'linda') ?? 'linda';

if (empty($db_name) || empty($db_pass) || empty($db_name)) {
	echo 'Please make sure your config file specifies a user, pass, and database' . PHP_EOL;
	exit;
}

// Address To Send Coins To (Actual Address Of ID Above)
$to_address = $conf->get('wallet_address');

if (empty($to_address)) {
	echo 'Please make sure your config file specifies a wallet address' . PHP_EOL;
	exit;
}

// The Wallet ID Where Your Coins Are (Usually 0)
$wallet_id = $conf->get('wallet_id', '0') ?? '0';


// Notify Email Address (Leave Blank For No Notifications)
$notify_address = $conf->get('notify_email');

// Transaction amount to move coins
$trans_amount = $conf->get('trans_amount', 0.0001) ?? 0.0001;

// Minimum Number Of Confirmations 
$min_confirms = 10;

// Mail Send Settings
$mail_host = $conf->get('email.host');
$mail_port = $conf->get('email.port');; 
$mail_encr = $conf->get('email.secure');
$mail_user = $conf->get('email.user');
$mail_pass = $conf->get('email.pass');

$sql_script =<<<EOD
DROP DATABASE IF EXISTS `$db_name`;

CREATE DATABASE `$db_name`;

USE `$db_name`;

CREATE TABLE `linda_transactions` (
	`id` int(11) NOT NULL,
	`account` varchar(4) NOT NULL default '0',
	`address` varchar(200) COLLATE utf8_bin NOT NULL default '',
	`category` varchar(50) COLLATE utf8_bin NOT NULL default '',
	`amount` varchar(50) COLLATE utf8_bin NOT NULL default '',
	`fee` varchar(50) COLLATE utf8_bin NOT NULL default '',
	`confirmations` varchar(50) COLLATE utf8_bin NOT NULL default '',
	`generated` varchar(50) COLLATE utf8_bin NOT NULL default '',
	`blockhash` varchar(200) COLLATE utf8_bin NOT NULL default '',
	`blockindex` varchar(200) COLLATE utf8_bin NOT NULL default '',
	`blocktime` varchar(200) COLLATE utf8_bin NOT NULL default '',
	`txid` varchar(200) COLLATE utf8_bin NOT NULL default '',
	`time` varchar(50) COLLATE utf8_bin NOT NULL default '',
	`timereceived` varchar(50) COLLATE utf8_bin NOT NULL default ''
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
	
	ALTER TABLE `linda_transactions`
	ADD PRIMARY KEY (`id`),
	ADD KEY `txid` (`txid`);
	
	ALTER TABLE `linda_transactions`
	MODIFY `id` int(11) NOT NULL AUTO_INCREMENT; COMMIT;
EOD;


/////////////////////////////////////
// End Settings (Do Not Change Below)
/////////////////////////////////////

// Connect To Database
$dsn = "mysql:host=$db_host;dbname=$db_name;";
$opt = [
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = NULL;

$opts = getopt(NULL, ['install']);

// Install opt was specified, try and install
if (array_key_exists('install', $opts)) {

	try {
		$dsn = "mysql:host=$db_host;";
		$pdo = new PDO($dsn, $db_user, $db_pass, $opt);
	
	} catch (Exception $e) {
	
		die("PDO MySQL failed: " . $e->getMessage());
	}
	exit;
}

try {
	$pdo = new PDO($dsn, $db_user, $db_pass, $opt);
	$pdo->exec($sql_script);

	echo "$db_name has been created! Coin control script is ready to run.";

	exit;

} catch (Exception $e) {

	die($e->getMessage());
}

// Let's Only Run The Coin Control If Needed
$run_control = false;

// Grab The Last 10 Transaction for Account
$output = shell_exec("$linda_path listtransactions $wallet_id 10");
$data = json_decode($output);

// Reset Email Data
$incoming = '';

// Was Anything Returned?
if (is_array($data)) {
	foreach ($data as $k => $v) {
		// Convert Object To Array
		$v = (array)$v;
		
		// Check Minimum Confirmations
		if ($v['confirmations'] >= $min_confirms) {
			// Check For Transaction
			$stmt = $pdo->prepare('SELECT 1 FROM `linda_transactions` WHERE `txid`=?');
			$stmt->execute([$v['txid']]);
			$txExists = $stmt->fetchColumn();
			
			// Transaction Doesn't Exist
			if (!$txExists) {
				// Prepare Data
				$set = ''; $values = array();
				foreach ($v as $key => $va1) {
					$set .= "`".str_replace("`", "``", $key)."`"."=:$key, ";
					$values[$key] = $va1;
				}
				
				// Remove Last Comma & Space
				$set = substr($set, 0, -2); 
				
				// Add Txn Entry
				$stmt = $pdo->prepare("INSERT INTO `linda_transactions` SET $set");
				$stmt->execute($values);
				
				if ($v['category'] == 'generate') {
					$incoming .= "Reward Received:\nTransaction ID: ".$v['txid']."\nAmount: ".$v['amount']."\n\n";
				}
				
				// Let's Run That Coin Control Command If It Was A Reward
				if ($v['category'] == 'generate') $run_control = true;
			}
		}
	}
	
	if ($run_control) {
		// Get Current Total Coins
		$output = shell_exec("$linda_path getinfo");
		$data = json_decode($output);
		
		// Convert Object To Array
		$data = (array)$data;
		
		// Deduct Fees
		$send_balance = $data['balance']-$trans_amount;
		
		$output = exec("$linda_path sendtoaddress $to_address $send_balance");
		
		// Send Notification
		if ($notify_address != '') {
			$mail = new PHPMailer();
			$mail->SetLanguage('en', 'mail/language/');
			$mail->IsHTML(false);
			
			$mail->setFrom($notify_address);
			$mail->addAddress($notify_address);
			$mail->SMTPSecure = $mail_encr;
			$mail->isSMTP();
			
			$mail->Host = $mail_host;
			$mail->Port = $mail_port;
			if ($mail_user != "") {
				$mail->Username = $mail_user;
				$mail->Password = $mail_pass;
				$mail->SMTPAuth = true;
			}
			
			$mail->Subject = "New Coin Control Transaction";
			$mail->Body = $incoming."Coin Control Initiated:\nTransaction ID: ".$output."\nAmount: ".$send_balance;
			$mail->send();
		}
	}
	
	echo "Done";
} else {
	echo "An error occurred!";
}
?>
