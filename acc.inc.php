<?
error_reporting('E_ALL');
ini_set('display_errors','On'); 


$group_totals = array('GRP_id' => 0, 'BTC_received' => 0, 'BTC_sent' => 0, 'BTC_balance' => 0);

$mtgox_currencys = array ('USD', 'GBP', 'EUR', 'AUD', 'CAD', 'CHF', 'CNY', 'DKK',
                          'HKD', 'JPY', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB');

$mtgox_url = 'https://mtgox.com/api/1/';
$mtgox_exchange_path = '/public/ticker';
$btc_url = 'https://btc-e.com/api/2/';
$btc_exchange_path = '/ticker';
$cryptsy_url = 'http://pubapi.cryptsy.com/api.php';

$blockchain_url = 'http://www.blockchain.info/';
$blockchain_addr_options = '?format=json&limit=0&filter=5';
$blockchain_addr_path = 'address/';

$exchange_rate = 0;
$btce_exchange_BTC = 0;
$btce_exchange_LTC = 0;
$currency_code = 'USD';
$currency_symbol = "";

$opts = array(
  'http' => array(
    'method'=>"GET",
    'user_agent'=> 'hashcash',
    'header'=>"Accept-language: en\r\n",
    'timeout' => 3
  )
);

function create_accounts_table()
{
  global $dbh;
  global $primary_key, $table_props;

    $tblstr = "
  CREATE TABLE IF NOT EXISTS `accounts` (
    `id` ".$primary_key.",
    `group` mediumint(6) DEFAULT '0',
    `name` varchar(255) NOT NULL,
    `address` varchar(34) NOT NULL,
		`cryptocoin` varchar(3) NOT NULL
  )".$table_props.";";

  $dbh->query($tblstr);
}

function create_accgroups_table()
{
  global $dbh;
  global $primary_key, $table_props;

    $tblstr = "
  CREATE TABLE IF NOT EXISTS `accgroups` (
    `id` ".$primary_key. ",
    `name` varchar(255) NOT NULL,
    `currency` varchar(3) NOT NULL DEFAULT 'USD'
  )".$table_props.";";

  $dbh->query($tblstr);
}

function create_group_header($group_data)
{
  global $group_totals;
  global $mtgox_url;
  global $mtgox_exchange_path;
	global $btc_url;
	global $btc_exchange_path;
  global $exchange_rate;
  global $btce_exchange_BTC;
	global $btce_exchange_LTC;
  global $cryptsy_exchange_VTC;
  global $currency_code;
  global $currency_symbol;
  global $opts;
  global $cryptsy_url;

	switch($currency_code) {
		case "USD":
			$currency_symbol = "$";
			break;
		default:
			$currency_symbol = "";
			break;
	 }
  /* reset BTC counters */
  $group_totals['GRP_id'] = $group_data['id'];
  $group_totals['BTC_received'] = 0;
  $group_totals['BTC_sent'] = 0;
  $group_totals['BTC_balance'] = 0;

  /* get exchange rate data from mtgox */
  $currency_code = $group_data['currency'];
  $url = $mtgox_url . "BTC" . $currency_code . $mtgox_exchange_path;
  $context  = stream_context_create($opts);
  $url_data = file_get_contents($url,false,$context);
  $mtgox_arr = json_decode($url_data, true);
  $exchange_rate = $mtgox_arr['return']['last_local']['value'];

	/* get exchange rate data from btc-e for BTC*/
	$url = $btc_url."btc_".strtolower($currency_code).$btc_exchange_path;
	$url_data = file_get_contents($url,false,$context);
	$btceBTC_arr = json_decode($url_data, true);
	$btce_exchange_BTC = $btceBTC_arr['ticker']['last'];

  /* get exchange rate data from btc-e for LTC*/
  $url = $btc_url."ltc_".strtolower($currency_code).$btc_exchange_path;
  $url_data = file_get_contents($url,false,$context);
  $btceLTC_arr = json_decode($url_data, true);
  $btce_exchange_LTC = $btceLTC_arr['ticker']['last'];

  /* get exchange rate data from cryptsy for VTC */
  $url = $cryptsy_url."?method=singlemarketdata&marketid="."151";
  $url_data = file_get_contents($url,false,$context);
  $cryptsyVTC_arr = json_decode($url_data, true);
  $cryptsy_exchange_VTC = $cryptsyVTC_arr['return']['markets']['VTC']['lasttradeprice'] * $btce_exchange_BTC;
  //echo "<pre>", print_r($cryptsyVTC_arr); exit();

	$line =
  "<tr>
    <th colspan='10'>".
      $group_data['name']
    ."</th>
  </tr>
  <tr>
    <th>
      &nbsp;
    </th>
    <th>
      Account Name
    </th>
    <th>
      Account Address
    </th>
		<th>
			Coin
		</th>
    <th>
      Received
    </th>
    <th>
      Sent
    </th>
    <th>
      Balance
    </th>
    <th>MTGOX<br />
      BTC->".$group_data['currency']." (".round($exchange_rate,2).")
    </th>
		<th>BTC-E<br />
			BTC->".$group_data['currency']." (".round($btce_exchange_BTC,2).")<br />
			LTC->".$group_data['currency']." (".round($btce_exchange_LTC,2).")
		</th>
   <th>Cryptsy<br />
     VTC->".$group_data['currency']." (".round($cryptsy_exchange_VTC,2).")
   </th>
  </tr>";
  
  return $line;
}


function get_acc_summary($acc_data)
{
  global $group_totals;
  global $blockchain_url;
  global $blockchain_addr_path;
  global $blockchain_addr_options;

  global $exchange_rate;
  global $btce_exchange_BTC;
  global $btce_exchange_LTC;
  global $cryptsy_exchange_VTC;
	global $currency_symbol;
  global $opts;

  /* get data of address from blockchain.info */
  $btc_address = $acc_data['address'];
  $cryptocoin = $acc_data['cryptocoin'];
 	$context  = stream_context_create($opts);

	if($cryptocoin == "BTC") {
  	$url = $blockchain_url . $blockchain_addr_path . $btc_address . $blockchain_addr_options;
  	$url_data = file_get_contents($url,false,$context);
  	$acc_arr = json_decode($url_data, true);
  	$btc_received = round($acc_arr['total_received'] / 100000000, 2);
  	$btc_sent = round($acc_arr['total_sent'] / 100000000, 2);
  	$btc_balance = round($acc_arr['final_balance'] / 100000000, 2);
  	$exchanged_balance = round($btc_balance * $exchange_rate, 2);
    $btce_exchange_balance = round($btc_balance * $btce_exchange_BTC, 2);
  	$group_totals['BTC_received'] += $btc_received;
  	$group_totals['BTC_sent'] += $btc_sent;
  	$group_totals['BTC_balance'] += $btc_balance;
		$group_totals['BTC_gox'] += round($exchanged_balance,2);
		$group_totals['BTC_bte'] += round($btce_exchange_balance,2); 

  } else if ($cryptocoin == "LTC") {
 		$exchange_balance = 0;
		$url = "http://explorer.litecoin.net/address/".$btc_address;
		$url_data = file_get_contents($url,false,$context);
		preg_match("/Received:\ ([\d.]*)/i", $url_data, $temp);
		$btc_received = round($temp[1],2);
		preg_match("/Sent:\ ([\d.]*)/i", $url_data, $temp);
		$btc_sent = round($temp[1],2);
		$btc_balance = $btc_received - $btc_sent;
		$exchanged_balance = 0;
		$btce_exchange_balance = round($btc_balance * $btce_exchange_LTC, 2);
		$group_totals['BTC_received'] += $btc_received;
		$group_totals['BTC_sent'] += $btc_sent;
		$group_totals['BTC_balance'] += $btc_balance;
    $group_totals['BTC_bte'] += round($btce_exchange_balance,2);
  } else {
    $exchange_balance = 0;
    $url = "http://explorer.vertcoin.org/address/".$btc_address;
    $url_data = file_get_contents($url,false,$context);
    preg_match("/Received:\ ([\d.]*)/i", $url_data, $temp);
    $btc_received = round($temp[1],2);
    preg_match("/Sent:\ ([\d.]*)/i", $url_data, $temp);
    $btc_sent = round($temp[1],2);
    $btc_balance = $btc_received - $btc_sent;
    $exchanged_balance = 0;
    $cryptsy_exchange_balance = round($btc_balance * $cryptsy_exchange_VTC, 2);
    $group_totals['BTC_received'] += $btc_received;
    $group_totals['BTC_sent'] += $btc_sent;
    $group_totals['BTC_balance'] += $btc_balance;
    $group_totals['BTC_cryptsy'] += round($cryptsy_exchange_balance,2);
	}
  $line =
  "<tr>
    <td>
      <input type='checkbox' name='del_acc[]' value='".$acc_data['id']."'>
    </td>
    <td>".
      $acc_data['name']
    ."</td>
    <td><a href='".$blockchain_url.$blockchain_addr_path.$btc_address."'>".
      $btc_address
    ."</a></td>
    <td>".
			$cryptocoin
		."</td>
		<td>".
      $btc_received
    ."</td>
    <td>".
      $btc_sent
    ."</td>
    <td>".
      $btc_balance
    ."</td>
    <td>";
	if($exchanged_balance == 0)
		$line .= "-</td><td>";
  else
		$line .= $currency_symbol.$exchanged_balance."</td><td>";
	if($btce_exchange_balance == 0)
		$line .= "-</td><td>";
	else
		$line .= $currency_symbol.$btce_exchange_balance."</td><td>";
  if($cryptsy_exchange_balance == 0)
    $line .= "-</td></tr>";
  else
    $line .= $currency_symbol.$cryptsy_exchange_balance."</td></tr>";


  return $line;
}

function create_group_totals()
{
  global $group_totals;
  global $currency_symbol;

  $line =
  "<tr>
    <th>
      <input type='checkbox' name='deletegrp' value='".$group_totals['GRP_id']."'>
    </th>
    <th colspan='2'><div style='text-align:right'>
     Totals:</div>
    </th>
		<th></th>
    <th>".
      $group_totals['BTC_received']
    ."</th>
    <th>".
      $group_totals['BTC_sent']
    ."</th>
    <th>".
      $group_totals['BTC_balance']
    ."</th>
    <th>";
if ($group_totals['BTC_gox'] == 0)
	$line .= "-</th><th>";
else
	$line .= $currency_symbol.$group_totals['BTC_gox']."</th><th>";
if ($group_totals['BTC_bte'] == 0)
	$line .= "-</th><th>";
else
	$line .= $currency_symbol.$group_totals['BTC_bte']."</th><th>";
if ($group_totals['BTC_cryptsy'] == 0)
  $line .= "-</th></tr>";
else
  $line .= $currency_symbol.$group_totals['BTC_cryptsy']."</th></tr>";

$line .= " <tr>
    <th colspan='10'>
      Name: <input type='text' name='name'>&nbsp;
      Address: <input type='text' name='address'>&nbsp;
			<select name='cryptocoin'>
				<option value='BTC'>BTC</option>
				<option value='LTC'>LTC</option>
        <option value='VTC'>VTC</option>
			</select>
      <input type='submit' value='Add Account' name='addacc'>
      <input type='hidden' name='groupid' value='".$group_totals['GRP_id']."'>
      &nbsp; &nbsp;
      <input type='submit' value='Delete selected' name='delete'>
    </th>
  </tr>
  ";

  return $line;
}


?>
