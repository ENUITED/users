<?php
  
  # Ensure we are serving admin
  require_once(dirname(dirname(dirname(__FILE__))).'/admin/admin.php');

  $ADMIN_SECTION = 'PaymentEngine_Manual';
  require_once(dirname(dirname(dirname(__FILE__))).'/admin/header.php');
  
  $action = isset($_REQUEST['action']) ? htmlspecialchars($_REQUEST['action']) : '';
  switch($action) {
  
    case 'ProcessAddPayment':
    
      $account_id = isset($_REQUEST['account_id']) ? intval(htmlspecialchars($_REQUEST['account_id'])) : NULL;
      $amount = isset($_REQUEST['howmuch']) ? htmlspecialchars($_REQUEST['howmuch']) : NULL;

      if (is_null($account = Account::getByID($account_id))) {
      ?>
        <p>Can't find account with ID <?php echo $account_id ?></p>
      <?php    
        break;
      } elseif(is_null($amount) || !is_numeric($amount)) {
      ?>
        <p>Please enter numeric value for amount.</p>
      <?php
      } else {
        $engine = $account->getPaymentEngine();
        if(is_null($engine)) {
        ?>
        <p>Can't find Payment Engine for account <?php echo $account_id ?>. Should be 'PaymentEngine_Manual'.</p>
        <?php
          break;
        } else {
          $data = array('account_id' => $account_id, 'amount' => $amount);
          if($engine->paymentReceived($data)) {
          ?>
            <p>Payment of $<?php echo $amount ?> recorded</p>
          <?php
          } else {
          ?>
            <p>Error recording payment!</p>
          <?php
          }
        }
      }


    case 'DisplayAddPayment':
    
      $account_id = isset($_REQUEST['account_id']) ? intval(htmlspecialchars($_REQUEST['account_id'])) : NULL;
      if (is_null($account = Account::getByID($account_id))) {
      ?>
        <p>Can't find account with ID <?php echo $account_id ?></p>
      <?php
        break;
      } else { 
        $balance = preg_replace("/(^|-)/","$$1",sprintf("%.2f",$account->getBalance()),1);
      ?>
      <div>
        <form action="">
        <input type="hidden" name="action" value="ProcessAddPayment" />
        <input type="hidden" name="account_id" value="<?php echo $account_id ?>" />
        <p>Add funds for account '<b><?php echo $account->getName() ?></b>' (Current Balance: <b><?php echo $balance ?></b>
          ID: <b><?php echo $account_id?>)</b></p>
        <p>How much:<input type="text" id="howmuch" name="howmuch" /></p>
        <p><input type="submit" value="Ok" /></p>
        </form>
      </div>
    
      <?php
      }
      break;
      
    default:

      # Display account list and filter form
      ?>
<table cellpadding="5" cellspacing="0" border="1" width="100%">
<tr><th>ID</th><th>Name</th><th>Plan</th><th>Schedule</th><th>Active</th><th>Balance</th><th>&nbsp;</th><th>&nbsp;</th></tr>
<?php
      $perpage = 20;  
      $pagenumber = 0;

      if (array_key_exists('page', $_GET))
        $pagenumber = $_GET['page'];

      $search = null;
      if (array_key_exists('q', $_GET)) {
        $search = trim($_GET['q']);
        if ($search == '')
          $search = null;
      }

      $sort_by = array(
        'id' => 'ID',
        'name' => 'Account Name',
        'plan' => 'Payment Plan',
        'schedule' => 'Payment Schedule',
        'active' => 'Account status',
        'balance' => 'Account balance');
        
      if (array_key_exists('sort', $_GET) && in_array($_GET['sort'],array_keys($sort_by)))
        $sortby = $_GET['sort'];
      else
        $sortby = 'id';

      $db = UserConfig::getDB();

      if (!($stmt = $db->prepare('SELECT id,name,plan,schedule,active,COALESCE(SUM(amount),0) AS balance FROM '.
        UserConfig::$mysql_prefix.'accounts AS a LEFT JOIN '.UserConfig::$mysql_prefix.'account_charge AS c '.
        'ON c.account_id = a.id WHERE engine = "PaymentEngine_Manual" '.(is_null($search) ? '' : 'AND name like ? ').
        'GROUP BY c.account_id ORDER BY '.$sortby.' LIMIT '.$perpage.' OFFSET '.$pagenumber * $perpage)))
          throw new Exception("Can't prepare statement: ".$db->error);

      if(!is_null($search)) {
        $search_like = '%'.$search.'%';
        if (!$stmt->bind_param('s',$search_like))
          throw new Exception("Can't bind parameter".$stmt->error);
      }

      if (!$stmt->execute())
        throw new Exception("Can't execute statement: ".$stmt->error);

      if (!$stmt->bind_result($id, $name, $plan, $schedule, $active, $balance))
        throw new Exception("Can't bind result: ".$stmt->error);

      $accounts = array();
      while($stmt->fetch() === TRUE)
        $accounts[] = array(
          'id' => $id, 
          'name' => $name, 
          'plan' => $plan, 
          'schedule' => $schedule, 
          'active' => $active, 
          'balance' => $balance);
          
      $stmt->close();
      ?>
      <tr><td colspan="8" valign="middle">
      <?php
      if (count($accounts) == $perpage) {
        ?><a style="float: right" href="?page=<?php echo $pagenumber+1; echo is_null($search) ? '' : '&q='.urlencode($search)?>">next &gt;&gt;&gt;<a><?php
      }
      else {   
        ?><span style="color: silver; float: right">next &gt;&gt;&gt;</span><?php
      }
      if ($pagenumber > 0) {
        ?><a style="float: left" href="?page=<?php echo $pagenumber-1; echo is_null($search) ? '' : '&q='.urlencode($search) ?>">&lt;&lt;&lt;prev</a><?php
      }
      else {   
        ?><span style="color: silver; float: left">&lt;&lt;&lt;prev</span><?php
      }
      ?>
      <span style="float: left; margin: 0 2em 0 1em;">Page <?php echo $pagenumber+1?></span>
      <form action="" id="search" name="search">
      <input type="text" id="q" name="q"<?php echo is_null($search) ? '' : ' value="'.htmlspecialchars($search).'"'?>/><input type="submit" value="search"/><input type="button" value="clear" onclick="document.getElementById('q').value=''; document.search.submit()"/>
      Sort by
      <select name="sort" onchange="document.search.submit();">
      <?php
        foreach($sort_by as $k => $v)
          echo "<option value=\"".$k."\" ".($sortby == $k ? ' selected="yes"' : '')." >".$v."</option>\n";
      ?>
      </select>
      </form>  
      </td></tr>
      <?php
      foreach($accounts as $a) {
      
        echo "<tr valign=\"top\">\n";
        echo "<td><a href=\"".UserConfig::$USERSROOTURL."/admin/account.php?account_id=".$a['id']."\">".$a['id']."</a></td>\n";
        echo "<td><a href=\"".UserConfig::$USERSROOTURL."/admin/account.php?account_id=".$a['id']."\">".$a['name']."</a></td>\n";
        echo "<td>".$a['plan']."</td><td>".$a['schedule']."</td><td>".($a['active'] ? 'Active' : 'Suspended')."</td>\n";
        $a['balance'] = preg_replace("/(^|-)/","$$1",sprintf("%.2f",-$a['balance']),1);
        echo "<td>".$a['balance']."</td><td><a href=\"?action=DisplayAddPayment&account_id=".$a['id']."\">Add funds</a></td>";
        echo "<td><a href=\"?action=Refund&account_id=".$a['id']."\">Refund</a></td></tr>\n";
      }
      ?>
      <tr><td colspan="8">
      <?php
      if (count($accounts) == $perpage) {
        ?><a style="float: right" href="?page=<?php echo $pagenumber+1; echo is_null($search) ? '' : '&q='.urlencode($search)?>">next &gt;&gt;&gt;</a><?php
      }
      else {   
        ?><span style="color: silver; float: right">next &gt;&gt;&gt;</span><?php
      }
       
      if ($pagenumber > 0) {
        ?><a style="float: left" href="?page=<?php echo $pagenumber-1; echo is_null($search) ? '' : '&q='.urlencode($search)?>">&lt;&lt;&lt;prev</a><?php
      }
      else {   
        ?><span style="color: silver; float: left">&lt;&lt;&lt;prev</span><?php
      }
      ?>
      <span style="float: left; margin-left: 2em">Page <?php echo $pagenumber+1?></span>

      </td></tr>
      </table>
      <?php
      
      # end of default
  }
  
  if($action != '')
    echo "<p><a href=\"".UserConfig::$USERSROOTURL."/modules/PaymentEngine_Manual/admin.php\">Back to list</a></p>\n";
  require_once(dirname(dirname(dirname(__FILE__))).'/admin/footer.php');
            