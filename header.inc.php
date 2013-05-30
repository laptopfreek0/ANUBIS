<?
$pages = array("Home" => "index.php",
             "Accounts" => "accounts.php",
             "Configuration" => "config.php",
             "FAQ" => "faq.php",
             "Contact/Donate" => "contact.php");

$page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);
?>
<div id="templatemo_header">

    <div id="site_title"><h1><a href="index.php">Main</a></h1></div>

    <div id="templatemo_menu" class="ddsmoothmenu">
      <ul>
<?
      foreach ($pages as $key => $value)
      {
        if  ($value == $page)
          $selected = "class='selected'";
        else
          $selected = "";

        echo "<li><a href='".$value."' ".$selected.">".$key."</a></li>";
      }
?>
<?
	$dbh = anubis_db_connect();
	$configq = $dbh->query('SELECT * FROM configuration');
	db_error();

	$config = $configq->fetch(PDO::FETCH_OBJ);
  switch($config->hashrate) {
    case 1000:
      $config->Hashrate = 'GH/s';
      break;
    case 1:
      $config->Hashrate = 'MH/s';
      break;
    case 0.001:
      $config->Hashrate = 'KH/s';
      break;
    default:
      $config->Hashrate = 'MH/s';
      break;
}
?>
    </ul>
    <br style="clear: left" />
  </div> <!-- end of templatemo_menu -->
        
</div> <!-- end of header -->

