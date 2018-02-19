<?php
/*
Copyright (C) Chris Park 2017
diskover is released under the Apache 2.0 license. See
LICENSE for the full license text.
 */

require '../vendor/autoload.php';
use diskover\Constants;
error_reporting(E_ALL ^ E_NOTICE);
require "../src/diskover/Diskover.php";

// unset command
if(isset($_GET['command'])) {
	unset($_GET['command']);
}

$host = Constants::ES_HOST;
$port = Constants::ES_PORT;

// check for index in url
if (isset($_GET['index'])) {
    $esIndex = $_GET['index'];
    setCookie('index', $esIndex);
} else {
    // redirect to select indices page if no index cookie
    $esIndex = getenv('APP_ES_INDEX') ?: getCookie('index');
    if (!$esIndex) {
        header("location:selectindices.php");
        exit();
    }
}
// check for index2 in url
if (isset($_GET['index2'])) {
    $esIndex2 = $_GET['index2'];
    setCookie('index2', $esIndex2);
} else {
    $esIndex2 = getenv('APP_ES_INDEX2') ?: getCookie('index2');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>diskover &mdash; Admin Panel</title>
<link rel="stylesheet" href="css/bootswatch.min.css" media="screen" />
<link rel="stylesheet" href="css/diskover.css" media="screen" />
<style>
pre {
    background-color:#060606!important;
    color: #56B6C2!important;
    border: none;
}
</style>
</head>

<body>
<?php include "nav.php"; ?>

<div class="container" style="margin-top:70px;">
<div class="row">
    <div class="col-xs-6">
        <h1 class="text-nowrap"><i class="glyphicon glyphicon-cog"></i> Admin Panel</h1>
        <span class="text-success"><?php echo "diskover-web v".Constants::VERSION; ?></span><br />
        <small><i class="glyphicon glyphicon-download-alt"></i> <a target="_blank" href="https://github.com/shirosaidev/diskover-web/releases/latest">Check for updates</a></small><br />
        Elasticsearch health: <span id="eshealthheart" style="font-size:18px;color:gray"><strong><i class="glyphicon glyphicon-heart-empty"></i></strong></span>
</div>
<div class="col-xs-6">
<pre>    __               __
   /\ \  __         /\ \
   \_\ \/\_\    ____\ \ \/'\     ___   __  __     __   _ __     //
   /'_` \/\ \  /',__\\ \ , <    / __`\/\ \/\ \  /'__`\/\`'__\  ('>
  /\ \L\ \ \ \/\__, `\\ \ \\`\ /\ \L\ \ \ \_/ |/\  __/\ \ \/   /rr
  \ \___,_\ \_\/\____/ \ \_\ \_\ \____/\ \___/ \ \____\\ \_\  *\))_
   \/__,_ /\/_/\/___/   \/_/\/_/\/___/  \/__/   \/____/ \/_/ v<?php echo CONSTANTS::VERSION; ?>
</pre>
<div class="text-center">
    <strong><i class="glyphicon glyphicon-heart"></i> Support diskover on <a target="_blank" href="https://www.patreon.com/diskover">Patreon</a> or <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CLF223XAS4W72" target="_blank">PayPal</a>.</strong>
</div>
</div>
</div>
<br />
<div class="row">
	<div class="col-xs-6">
        <div class="well">
            <h5>diskover indices selected</h5>
            Index: <?php echo $esIndex; ?><br />
            Index 2: <?php echo $esIndex2; ?><br />
            <small><a href="selectindices.php">Change</a></small>
        </div>

        <hr />
        <?php
            // Get cURL resource
            $curl = curl_init();
            // Set curl options
            curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => 'http://'.$host.':'.$port.'/diskover-*?pretty'
            ));
            // Send the request & save response to $curlresp
            $curlresp = curl_exec($curl);
            $json = json_decode($curlresp, true);
            $fields_file = $json[$esIndex]['mappings']['file']['properties'];
            $fields_dir = $json[$esIndex]['mappings']['directory']['properties'];
            // combine file and directory fields and find unique
            $fields = [];
            foreach ($fields_file as $key => $value) {
                $fields[] = $key;
            }
            foreach ($fields_dir as $key => $value) {
                $fields[] = $key;
            }
            $fields = array_unique($fields);
            // Close request to clear up some resources
            curl_close($curl);
        ?>
        <h4>Additional fields for search results</h4>
        <fieldset>
			<div class="form-group">
                <?php for ($i=1; $i < 5; $i++) { ?>
                    <label for="field<?php echo $i; ?>">field <?php echo $i; ?></label>
            		<select class="form-control" id="field<?php echo $i; ?>" name="field<?php echo $i; ?>" style="width:200px; display: inline;">
            		  <option value="<?php echo getCookie('field'.$i.''); ?>" selected><?php echo getCookie('field'.$i.''); ?></option>
                      <?php foreach ($fields as $key => $value) { ?>
                          <option value="<?php echo $value; ?>"><?php echo $value; ?></option>
                      <?php } ?>
            		</select>
    				<input style="width:200px; display: inline;" name="field<?php echo $i; ?>-desc" type="text" id="field<?php echo $i; ?>-desc" placeholder="header name" class="form-control" value="<?php echo getCookie('field'.$i.'-desc'); ?>" /><br />
                <?php } ?>
			</div>
			<div class="form-group">
                <button type="submit" class="btn btn-primary" onclick="clearFields()">Clear all</button>
				<button type="submit" class="btn btn-primary" onclick="setFields()">Set</button>
			</div>
		</fieldset>
    </div>
    <div class="col-xs-6">
        <div class="form-group">
        <h4>Edit smart searches</h4>
        <span style="color:#666;">!name|es query string</span>
		<?php

// configuration
$file_smartsearches = 'smartsearches.txt';

// check if form has been submitted
if (isset($_POST['smartsearchtext'])) {
    // save the text contents
    $smartsearchtext = $_POST['smartsearchtext'];
    // check for newline at end
    if (substr($smartsearchtext, -1) != PHP_EOL) {
        // add newline
        $smartsearchtext .= PHP_EOL;
    }
    file_put_contents($file_smartsearches, $smartsearchtext);
		$smartsearchsaved = TRUE;
}

// read the textfile
$smartsearchtext = file_get_contents($file_smartsearches);

?>
			<form name="editsmartsearch" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" class="form-horizontal">
				<fieldset>
					<div class="col-xs-12">
						<div class="form-group">
							<textarea class="form-control" rows="15" name="smartsearchtext"><?php echo htmlspecialchars($smartsearchtext) ?></textarea>
						</div>
						<div class="form-group">
							<button type="reset" class="btn btn-default">Cancel</button>
							<button type="submit" class="btn btn-primary">Save</button>
                            <?php if ($smartsearchsaved) { ?><script>alert("smart searches saved");</script><?php } ?>
						</div>
					</div>
				</fieldset>
			</form>
        </div>
</div>
</div>
<div class="row">
<div class="col-xs-6">
        <br />
        <div class="well">
			<h5>diskover socket server status</h5>
		<?php
error_reporting(E_ERROR | E_PARSE);
// open socket connection to diskover listener
$socket_host = Constants::SOCKET_LISTENER_HOST;
$socket_port = Constants::SOCKET_LISTENER_PORT;
$fp = stream_socket_client("tcp://".$socket_host.":".$socket_port, $errno, $errstr, 10);
// test if listening
fwrite($fp, "ping");
$result = fgets($fp, 1024);
// close socket
fclose($fp);
if ($result == "pong") {
	$socketlistening = 1;
	echo '<span class="label label-success"><i class="glyphicon glyphicon-off"></i> diskover.py listening on port '.$socket_port.' TCP</span><input type="hidden" id="socketlistening" value="'.$socketlistening.'" />';
} else {
	$socketlistening = 0;
	echo '<span class="label label-warning"><i class="glyphicon glyphicon-off"></i> diskover.py not listening</span><input type="hidden" id="socketlistening" value="'.$socketlistening.'" />';
}
?>
<br /><br />
<button type="submit" class="btn btn-primary btn-sm" onclick="location.reload(true)" title="reload"><i class="glyphicon glyphicon-refresh"></i> </button>

<h4>Run diskover socket command</h4>
<fieldset>
    <div class="form-group">
        <select class="form-control" id="commandset" name="commandset" style="width:200px; display: inline;">
            <?php $cmd = '{"action": "finddupes", "index": "'.$esIndex.'"}'; ?>
          <option value='<?php echo $cmd; ?>'>Find duplicate files</option>
            <?php $cmd = '{"action": "dirsize", "index": "'.$esIndex.'"}'; ?>
          <option value='<?php echo $cmd; ?>'>Calculate all directory sizes/items</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary run-btn" onclick="runCommand(JSON.parse(document.getElementById('commandset').value))">Run</button>
    </div>
    <div class="form-group">
        <input name="command" type="text" id="command" placeholder="Command" class="form-control" size="80" />
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary run-btn" onclick="runCommand(JSON.parse(document.getElementById('command').value))">Run</button>
    </div>
</fieldset>

</div>
        <br />
		<h4>Edit diskover-web config</h4>
		<?php

// configuration
$file_config = '../src/diskover/Constants.php';

// check if form has been submitted
if (isset($_POST['configtext'])) {
    // save the text contents
    file_put_contents($file_config, $_POST['configtext']);
		$configsaved = TRUE;
}

// read the textfile
$configtext = file_get_contents($file_config);

?>
			<form name="editconfig" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" class="form-horizontal">
				<fieldset>
					<div class="col-xs-12">
						<div class="form-group">
							<textarea class="form-control" rows="18" name="configtext"><?php echo htmlspecialchars($configtext) ?></textarea>
						</div>
						<div class="form-group">
							<button type="reset" class="btn btn-default">Cancel</button>
							<button type="submit" class="btn btn-primary">Save</button>
                            <?php if ($configsaved) { ?><script>alert("config saved");</script><?php } ?>
						</div>
					</div>
				</fieldset>
			</form>

        <hr />
		<h4>Clear diskover cookies/cache</h4>
		<button type="submit" class="btn btn-primary" onclick=clearCache()>Clear</button>

        <hr />
        <h4>Delete diskover indices</h4>
        <span style="color:red"><strong>*careful, index will be deleted permanently</strong></span>
    	<form "deleteindices" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" class="form-horizontal">
    	<fieldset>
            <div class="col-xs-12">
    		<div class="form-group">
    			<?php
    				// delete indices
    				if (isset($_POST['indices'])) {
    					foreach ($_POST['indices'] as $i) {
    						// Get cURL resource
    						$curl = curl_init();
    						// Set curl options
    						curl_setopt_array($curl, array(
    								CURLOPT_RETURNTRANSFER => 1,
    								CURLOPT_CUSTOMREQUEST => 'DELETE',
    								CURLOPT_URL => 'http://'.$host.':'.$port.'/'.$i.'?pretty'
    						));
    						// Send the request & save response to $curlresp
    						$curlresp = curl_exec($curl);
    						// Close request to clear up some resources
    						curl_close($curl);
    					}
    				}

    				// Get cURL resource
    				$curl = curl_init();
    				// Set curl options
    				curl_setopt_array($curl, array(
    						CURLOPT_RETURNTRANSFER => 1,
    						CURLOPT_URL => 'http://'.$host.':'.$port.'/diskover-*?pretty'
    				));
    				// Send the request & save response to $curlresp
    				$curlresp = curl_exec($curl);
    				$indices = json_decode($curlresp, true);

    				// Close request to clear up some resources
    				curl_close($curl);
    				?>
    			<select multiple="" name="indices[]" id="indices" class="form-control"><?php
    				foreach ($indices as $key => $val) {
    					echo "<option>".$key."</option>";
    				}
    				?></select>
    		</div>
    		<div class="form-group">
    			<button type="submit" class="btn btn-danger" onclick="delIndex()">Delete</button>
    		</div>
        </div>
        </fieldset>
    </form>
</div>

<div class="col-xs-6">
        <div class="form-group">
        <hr />
        <h4>Edit custom tags</h4>
        <span style="color:#666;">tag name|#hexcolor</span>
		<?php

// configuration
$file_customtags = 'customtags.txt';

// check if form has been submitted
if (isset($_POST['tagtext'])) {
    // save the text contents
    $tagtext = $_POST['tagtext'];
    // check for newline at end
    if (substr($tagtext, -1) != PHP_EOL) {
        // add newline
        $tagtext .= PHP_EOL;
    }
    file_put_contents($file_customtags, $tagtext);
		$tagssaved = TRUE;
}

// read the textfile
$tagtext = file_get_contents($file_customtags);

?>
			<form "edittags" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" class="form-horizontal">
				<fieldset>
					<div class="col-xs-12">
						<div class="form-group">
							<textarea class="form-control" rows="15" name="tagtext"><?php echo htmlspecialchars($tagtext) ?></textarea>
						</div>
						<div class="form-group">
							<button type="reset" class="btn btn-default">Cancel</button>
							<button type="submit" class="btn btn-primary">Save</button>
                            <?php if ($tagssaved) { ?><script>alert("tags saved");</script><?php } ?>
						</div>
					</div>
				</fieldset>
			</form>
        </div>

		<div class="form-group">
            <?php
            // Get cURL resource
            $curl = curl_init();
            // Set curl options
            curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => 'http://'.$host.':'.$port.'/diskover-*/_stats?pretty'
            ));
            // Send the request & save response to $curlresp_esinfo
            $curlresp_esinfo = curl_exec($curl);
            // Close request to clear up some resources
            curl_close($curl);

            // Get cURL resource
            $curl = curl_init();
            // Set curl options
            curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => 'http://'.$host.':'.$port.'/_cat/indices?v'
            ));
            // Send the request & save response to $curlresp_esinfo
            $curlresp_eshealth = curl_exec($curl);
            // Close request to clear up some resources
            curl_close($curl);
            ?>
            <hr />
            <h4>Index info</h4>
			<textarea name="curlresp" id="curlresp" class="form-control" rows=12><?php echo htmlspecialchars($curlresp) ?></textarea>
		</div>

		<div class="form-group">
            <hr />
            <h4>Elasticsearch info</h4>
			<textarea name="curlresp_esinfo" id="curlresp_esinfo" class="form-control" rows=12><?php echo htmlspecialchars($curlresp_esinfo) ?></textarea>
		</div>
    </div>
    </fieldset>
    </form>
</div>
<div class="row">
	<div class="col-xs-12">
        <hr />
		<h4 style="display:inline-block">Elasticsearch health / index sizes</h4>
        <?php
        if (strpos($curlresp_eshealth, 'green')) {
            $eshealth = 'green';
        } elseif(strpos($curlresp_eshealth, 'yellow')) {
            $eshealth = 'yellow';
        } elseif(strpos($curlresp_eshealth, 'red')) {
            $eshealth = 'red';
        } else {
            $eshealth = 'gray';
        }
        ?>
        &nbsp;&nbsp;<span style="font-size:24px;color:<?php echo $eshealth ?>"><strong><i class="glyphicon glyphicon-heart-empty"></i></strong></span>
		<div class="form-group">
            <input type="hidden" name="eshealth" id="eshealth" value="<?php echo $eshealth ?>" />
			<textarea name="curlresp_eshealth" id="curlresp_eshealth" class="form-control" rows=12><?php echo htmlspecialchars($curlresp_eshealth) ?></textarea>
		</div>
        <br />
	</div>
</div>
<div class="alert alert-dismissible alert-danger" id="errormsg-container" style="display:none; width:400px; position: fixed; right: 50px; bottom: 20px; z-index:2">
            <button type="button" class="close" data-dismiss="alert">&times;</button><strong><span id="errormsg"></span></strong>
</div>
<div id="progress-container" class="alert alert-dismissible alert-info" style="display:none; width:400px; height:80px; position: fixed; right: 50px; bottom: 20px; z-index:2">
  <strong>Task running</strong>, keep this window open until done.<br />
  <div id="progress" class="progress">
    <div id="progressbar" class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="min-width: 2em; width: 0%; color:white; font-weight:bold; height:20px;">
      0%
    </div>
  </div>
</div>
</div>

<script language="javascript" src="js/jquery.min.js"></script>
<script language="javascript" src="js/bootstrap.min.js"></script>
<script language="javascript" src="js/diskover.js"></script>
<script>
// clear diskover cookies/cache
function clearCache() {
	console.log("purging cookies/cache");
	deleteCookie('filter');
	deleteCookie('mtime');
    deleteCookie('maxdepth');
	deleteCookie('hide_thresh');
	deleteCookie('path');
	deleteCookie('use_count');
	deleteCookie('sort');
	deleteCookie('sortorder');
    deleteCookie('sort2');
    deleteCookie('sortorder2');
    deleteCookie('resultsize');
    deleteCookie('index');
    deleteCookie('index2');
    deleteCookie('field1');
    deleteCookie('field2');
    deleteCookie('field3');
    deleteCookie('field4');
    deleteCookie('field1-desc');
    deleteCookie('field2-desc');
    deleteCookie('field3-desc');
    deleteCookie('field4-desc');
    deleteCookie('PHPSESSID');
	sessionStorage.removeItem("diskover-filetree");
	sessionStorage.removeItem("diskover-treemap");
    sessionStorage.removeItem("diskover-heatmap");
	alert('cleared, please restart browser');
    return true;
}

// set custom fields
function setFields() {
    var fields = [];
    var fields_desc = [];
	fields[0] = document.getElementById('field1').value;
    fields[1] = document.getElementById('field2').value;
    fields[2] = document.getElementById('field3').value;
    fields[3] = document.getElementById('field4').value;
    fields_desc[0] = document.getElementById('field1-desc').value;
    fields_desc[1] = document.getElementById('field2-desc').value;
    fields_desc[2] = document.getElementById('field3-desc').value;
    fields_desc[3] = document.getElementById('field4-desc').value;
	if (fields[0] == "") {
        alert("no fields selected")
		return false;
	} else {
        (fields[0] != "") ? setCookie('field1', fields[0]) : '';
        (fields[1] != "") ? setCookie('field2', fields[1]) : '';
        (fields[2] != "") ? setCookie('field3', fields[2]) : '';
        (fields[3] != "") ? setCookie('field4', fields[3]) : '';
        (fields_desc[0] != "") ? setCookie('field1-desc', fields_desc[0]) : '';
        (fields_desc[1] != "") ? setCookie('field2-desc', fields_desc[1]) : '';
        (fields_desc[2] != "") ? setCookie('field3-desc', fields_desc[2]) : '';
        (fields_desc[3] != "") ? setCookie('field4-desc', fields_desc[3]) : '';
        deleteCookie('sort');
        deleteCookie('sort2');
        deleteCookie('sortorder');
        deleteCookie('sortorder2');
        alert("fields have been set")
		return true;
    }
}

// clear all custom fields
function clearFields() {
    deleteCookie('field1');
    deleteCookie('field2');
    deleteCookie('field3');
    deleteCookie('field4');
    deleteCookie('field1-desc');
    deleteCookie('field2-desc');
    deleteCookie('field3-desc');
    deleteCookie('field4-desc');
    deleteCookie('sort');
    deleteCookie('sort2');
    deleteCookie('sortorder');
    deleteCookie('sortorder2');
    alert("fields have been cleared")
	return true;
}

// Curl command
function delIndex() {
	var indices = document.getElementById('indices').value;
	if (!indices) {
		alert("select at least one index")
		return false;
	}
}
// listen for msgs from diskover socket server
listenSocketServer();
</script>

<script>
// set es health heart color at top of page
var color = document.getElementById('eshealth').value;
document.getElementById('eshealthheart').style.color=color;
</script>
</body>

</html>
