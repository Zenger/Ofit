<?php 
/* One File Issue Tracker */
/* Configure OFit here */

$config = array(
	//'dsn'      =>'mysql:host=localhost;dbname=example', // MySQL DSN Example
	'dsn'      => 'sqlite:./ofit.sqlite3', // SQLite DSN example, writes to ofit.sqlite3 file
	'username' => 'root',
	'password' => 'vertrigo',

	// Allow registrations ?
	'registration' => true,
	'forceEmailActivation' => false, // @TODO: Validate e-mails by sending an activation link
);

date_default_timezone_set('Europe/Chisinau'); // Set this if you want to affect the hours

/* Stop editing Ofit now */




/* Edit this next part only if you know what you're doing */

define('OFIT_THEME', 'amelia'); // Bootswatch theme
define('OFIT_VERSION' , '1.2'); // Version
/*
There are a few "MAGIC" functions that you can replaces.
Create a function called
Ofit_hash($str)   to change the hashing algorythm, by default Ofit uses sha1
Ofit_filter($str) to change the filtering algorythm, by default it uses filter_var 
Ofit_markdown($str), I wanted to add markdown support but I would have to attach a library. So now you can do it
 */
if (!session_id()) session_start();
class Ofit {
	public static $script;
	public static $path;
	public static $pdo;
	public static $on = false;
	public static $action;
	public static $isLoggedIn = false;
	public static $userID = 0;
	public static $role = 0;
	public static $registrationOpen = false;
	public static $isPageLocked = true; // By default everything is closed
	public static $roleNames = array(
		0  => 'Guest',
		5  => 'Contribuitor',
		10 => 'Owner'
	);

	public static function init() {
		self::$script = basename($_SERVER['SCRIPT_FILENAME']);
		self::$path   = realpath( dirname(__FILE__) );
	
		global $config;

		try {
			self::$pdo = new PDO($config['dsn']);
			self::$pdo->setAttribute(PDO::ATTR_ERRMODE, 
                              PDO::ERRMODE_EXCEPTION);
		} catch(Exception $e)
		{
			die( $e->getMessage() );
		}

		if (isset($_GET['on']))
		{
			self::$on = filter_var($_GET['on'], FILTER_SANITIZE_SPECIAL_CHARS);
		}

    	self::$action =  (isset($_GET['action'])) ? filter_var($_GET['action'], FILTER_SANITIZE_SPECIAL_CHARS) : false;

    	if (isset($_SESSION['ofit_user'])) { self::$userID = (int)$_SESSION['ofit_user']; self::$isLoggedIn = true; }

    	

    	if (@$config['registration'] == true) 
    	{
    		self::$registrationOpen = true;
    		if (Ofit::$action == "register" && Ofit::$on = "users") self::$isPageLocked = false;
    	}

    	if (isset($_SESSION['ofit_user'])) self::$isPageLocked = false;


    	if (isset($_POST['action']))
    	{
    		self::handlePostAction();
    	}
	}

	public static function Notice($text = false, $type = "error") {
		if ($text) 
		{
			$_SESSION['ofit_notice'] = array($type, $text);
		}
		else
		{
			$notice = @$_SESSION['ofit_notice'];
			if (is_array($notice) && !empty($notice))
			{
				echo "<br /><div class='alert alert-".$notice[0]."'>".$notice[1]."</div>";
				unset($_SESSION['ofit_notice']);
			}
		}
	}

	public static function getUsers()
	{    
    		return self::$pdo->query("SELECT ID, user_name, user_email FROM users ORDER BY id")->fetchAll();
	}

	public static function getUserByID($user_id)
	{
		$stmt = self::$pdo->prepare("SELECT ID, user_name, user_email FROM users WHERE ID = :id ");
		$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetch();
	}

	public static function getUserByEmail($user_email)
	{
		$stmt = self::$pdo->prepare("SELECT ID, user_name, user_email, user_pass FROM users WHERE user_email = :email ");
		$stmt->bindParam(':email', $user_email, PDO::PARAM_STR);
		$stmt->execute();
		return $stmt->fetch();
	}

	public static function getProjects($user = false)
	{
		if (!$user)	return self::$pdo->query("SELECT ID, project_name, user_id FROM projects ORDER BY id")->fetchAll();
		else {
			$stmt = self::$pdo->prepare("SELECT ID, project_name, user_id FROM projects WHERE user_id = :id ORDER BY id");
			$stmt->bindParam(":id", $user, PDO::PARAM_INT);
			$stmt->execute();
			return $stmt->fetchAll();
		}
	}


	public static function getProjectByID($project_id)
	{
	    $stmt = self::$pdo->prepare("SELECT ID, project_name, user_id FROM projects WHERE ID = :id ");
		$stmt->bindParam(':id', $project_id, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetch();
	}

	public static function getLabels( $id )
	{
		$stmt = self::$pdo->prepare("SELECT ID, label_name FROM labels WHERE project_id = :id ");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}



	public static function getIssues( $id , $label = false, $state = false)
	{
		$qs = "SELECT ID, issue_name, issue_text, user_id FROM issues WHERE project_id = :id";
		if ($label)
		{
			$qs .= " AND label_id = :label ";
		}

		$state = ($state === false) ? 1 : 0;

		if (isset($_GET['state'])) $state = (int)$_GET['state'];
		

		$qs .= " AND issue_state = :state ";
		$qs .= " ORDER BY ID DESC";

		$stmt = self::$pdo->prepare($qs);
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);

		if ($label)
		{
			$stmt->bindParam(":label", $label, PDO::PARAM_INT);
		}

		
		$stmt->bindParam(":state", $state, PDO::PARAM_INT);
		

		
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public static function getIssuesByUser( $id )
	{
		$stmt = self::$pdo->prepare("SELECT ID, issue_name, issue_text, user_id FROM issues WHERE user_id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);

		$stmt->execute();
		return $stmt->fetchAll();
	}

	public static function getCommentsByUser( $id )
	{
		$stmt = self::$pdo->prepare("SELECT ID, comment_text, user_id, issue_id FROM comments WHERE user_id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);

		$stmt->execute();
		return $stmt->fetchAll();
	}



	public static function getIssueByID($id)
	{
		$stmt = self::$pdo->prepare("SELECT ID, issue_name, issue_text, label_id, user_id, created_at, issue_state, project_id FROM issues WHERE ID = :id ");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetch();
	}

	public static function getComments($id)
	{
		$stmt = self::$pdo->prepare("SELECT ID, comment_text, user_id, created_at FROM comments WHERE issue_id = :id ORDER BY ID");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public static function post_value($key) 
	{
		if (isset($_POST[$key]))
		{
			echo " value='". filter_var( $_POST[$key] , FILTER_SANITIZE_SPECIAL_CHARS )."' ";
		}
	}


	public static function addUrlArg($arg, $value)
	{
		$url = parse_url( $_SERVER['REQUEST_URI'] );
		if (!isset($url['query'])) return $url['path'] . '?' . $arg . '=' . $value;
		parse_str($url['query'] , $args );
		@$args[$arg] = $value;

		$qs = "";
		if (!empty($args))
		{
			$i = 0;
			foreach($args as $k => $v)
			{	
				($i == 0) ? $qs .= "?" : $qs .= "&";
				$qs .=  $k . "=" . $v; 
				$i++;
			}
		}

		return $url['path'] . $qs;
	}

	public static function selected($value, $current, $echo = false)
	{
		$html = '';
		if ($value == $current) {
			$html = ' selected="selected" ';
		}
		if ($echo) echo $html;
		return $html;
	}

	public static function getUserAvatar($user_id)
	{
		$stmt = self::$pdo->prepare("SELECT ID, user_email FROM users WHERE ID = :id ");
		$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
		$stmt->execute();

		$q =  $stmt->fetch();
		$email = @$q['user_email'];
		echo "<img class='thumbnail' src='" . "http://www.gravatar.com/avatar/" . md5( strtolower( trim( $email ) ) ) . "?d=" . urlencode( "http://placehold.it/80x80&text=None&format=.png" ) . "&s=80" . "' />";
	}

	public static function ago($time)
	{
	   $time = strtotime($time);

	   $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
	   $lengths = array("60","60","24","7","4.35","12","10");

	   $now = time();

	       $difference     = $now - $time;
	       $tense         = "ago";

	   for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
	       $difference /= $lengths[$j];
	   }

	   $difference = round($difference);

	   if($difference != 1) {
	       $periods[$j].= "s";
	   }

	   return "$difference $periods[$j] ago ";
	}

	public static function cutWords($string, $wordsreturned)
	{
		$retval = $string;  //  Just in case of a problem
		$array = explode(" ", $string);
		if (count($array)<=$wordsreturned)
		{
			$retval = $string;
		}
		else
		{
			array_splice($array, $wordsreturned);
			$retval = implode(" ", $array)." ...";
		}
		return $retval;
	}

	public static function _esc( $str ) 
	{
		if (function_exists('Ofit_filter')) return Ofit_filter($str);
		return filter_var($str, FILTER_SANITIZE_SPECIAL_CHARS);
	}
	public static function _hash( $str )
	{
		if (function_exists('Ofit_hash')) return Ofit_hash($str);
		return sha1($str);
	}
	public static function _markdown($str)
	{
		if (function_exists('Ofit_markdown')) return Ofit_markdown($str);
		return $str; // plain
	}
	public static function handlePostAction()
	{
		

		if ($_POST['action'] == "users_login")
		{

			$email = Ofit::_esc($_POST['post_email']);
			$pwd   = Ofit::_hash( $_POST['post_password'] );

			$user = Ofit::getUserByEmail( $email );
			$wrongPassword = true;
			if (isset($user['user_pass']))
			{
				if ($user['user_pass'] === $pwd) $wrongPassword = false;
			}

			

			if (!$user || $wrongPassword) 
			{
				Ofit::Notice('Invalid e-mail or password.', 'danger');
			}
			else
			{
				$_SESSION['ofit_user'] = $user['ID'];

			}
		}

		if ($_POST['action'] == "issues_create")
		{
			$name = Ofit::_esc( $_POST['post_issue_name'] );
			$text = Ofit::_esc( $_POST['post_issue_text'] );
			$pid = $id = (int)$_GET['ID'];

			$date = date('Y-m-d H:I:s');

			$stmt = self::$pdo->prepare("INSERT INTO issues (`project_id`, `user_id`, `issue_name`, `issue_text`, `issue_state`, `created_at`) VALUES (:pid, :uid, :isn, :ist, 1, :d) ");
			$stmt->bindParam(':pid', $pid, PDO::PARAM_INT );
			$stmt->bindParam(':uid', self::$userID, PDO::PARAM_INT);
			$stmt->bindParam(':isn', $name, PDO::PARAM_STR);
			$stmt->bindParam(':ist', $text, PDO::PARAM_STR);
			$stmt->bindParam(':d'  , $date, PDO::PARAM_STR);

			$query = $stmt->execute();

			if ($query) {
				return Ofit::Notice('Your issue has been created', 'success');
			}

			return Ofit::Notice('There has been an error creating your issue', 'danger');

		}


		if ($_POST['action'] == "issues_close" || $_POST['action'] == "issues_open")
		{			
			// Has access to close this issue ?
			$issue = Ofit::getIssueByID( (int)$_GET['ID'] );

			$query = Ofit::hasAccesTo(self::$userID, $issue['project_id'] );
		

			$type = ($_POST['action'] == "issues_close") ? "close" : "open";
			if ($query)
			{
				$issue_id = (int)$_GET['ID'];
				(int)$state = !$issue['issue_state'];
				$stmt = self::$pdo->prepare("UPDATE issues SET issue_state = :s WHERE ID = :id");
				$stmt->bindParam(":id", $issue_id, PDO::PARAM_INT);
				$stmt->bindParam(":s", $state, PDO::PARAM_INT);

				$comment_text = trim( @Ofit::_esc($_POST['comment_text']) );
				if (!empty($comment_text)) 
				{
					Ofit::insertComment($comment_text);
				}

				if ($stmt->execute())
				{
					if ($type == "close") return Ofit::Notice('Issue has been closed', 'success');
					else return Ofit::Notice("Issue has been re-opened!", 'success');
				}
				return Ofit::Notice('There was an error. Please try again later.');
			}

			

			
		}

		if ($_POST['action'] == "comments_create")
		{
			$comment_text = trim( @Ofit::_esc($_POST['comment_text']) );
			if (!empty($comment_text))
			{
				Ofit::insertComment($comment_text);
			}
		}

		if ($_POST['action'] == "users_register")
		{
			if (!Ofit::$registrationOpen) return; // ;(
			$name  = Ofit::_esc($_POST['post_name']);
			$email = Ofit::_esc($_POST['post_email']);
			$p     = Ofit::_esc($_POST['post_password']);
			$p2    = Ofit::_esc($_POST['post_password2']);

			
			if (empty($name) || empty($email) || empty($p)) return Ofit::Notice('Please fill the required fields.', 'danger');

			if (strlen($p) < 6 || $p !== $p2) return Ofit::Notice('Invalid password or passwords do not match', 'danger');

			$date = date('Y-m-d H:I:s');
			$p = Ofit::_hash($p);

			$stmt = self::$pdo->prepare("INSERT INTO users (`user_name`, `user_email`, `user_pass`, `created_at`) VALUES (:n, :e, :p, :d) ");
			$stmt->bindParam(":n", $name,  PDO::PARAM_STR);
			$stmt->bindParam(":e", $email, PDO::PARAM_STR);
			$stmt->bindParam(":p", $p,     PDO::PARAM_STR);
			$stmt->bindParam(":d", $date,  PDO::PARAM_STR);
			if ($stmt->execute()) return Ofit::Notice('Thank you. You can now log in using your user.', 'success');
			return Ofit::Notice('There was an error creating your user!' ,'danger');
		}

		if ($_POST['action'] == "projects_create")
		{
			$project_name = Ofit::_esc($_POST['post_project_name']);
			if (empty($project_name)) return Ofit::Notice('Please enter the project name!', 'danger');

			$date = date('Y-m-d H:I:s');
			$stmt = self::$pdo->prepare("INSERT INTO projects (`project_name`, `user_id`, `created_at`) VALUES (:pn, :u, :d) ");
			$stmt->bindParam(":pn", $project_name, PDO::PARAM_STR);
			$stmt->bindParam(":u", self::$userID, PDO::PARAM_INT);
			$stmt->bindParam(":d", $date, PDO::PARAM_STR );
			if ($stmt->execute()) return Ofit::Notice( sprintf("Project \"%s\" has been created!", $project_name), 'success');
			return Ofit::Notice('There was an error creating your project', 'danger');
		}


		if ($_POST['action'] == "labels_create")
		{
			$label = Ofit::_esc($_POST['post_label']);
			if (empty($label)) return Ofit::Notice('Please enter the project name!', 'danger');

			$date = date('Y-m-d H:I:s');
			$id = (int)$_GET['ID'];
			$stmt = self::$pdo->prepare("INSERT INTO labels (`label_name`, `project_id`, `created_at`) VALUES (:label, :p, :d) ");
			$stmt->bindParam(":label", $label, PDO::PARAM_STR);
			$stmt->bindParam(":p", $id, PDO::PARAM_INT);
			$stmt->bindParam(":d", $date, PDO::PARAM_STR );


			if ($stmt->execute()) return Ofit::Notice( sprintf("Label \"%s\" has been created!", $label), 'success');
			return Ofit::Notice('There was an error creating your label', 'danger');
		}

		
	}

	public static function canCreateIssue($project_id = false)
	{
		// Check if user is owner of project
		if (!$project_id) $project_id = (int)$_GET['ID'];
		$project = Ofit::getProjectByID($project_id);
		if ((int)$project['ID'] == self::$userID) return true;

		// Check for access table
		$query = Ofit::hasAccesTo( self::$userID , $project_id );

		if (!$query) return false;
		return true;
	}


	public static function hasAccesTo( $user_id = false, $project_id = false, $returnAccessLevel = false)
	{
		if (!$user_id)    $user_id = self::$userID;
		if (!$project_id) $project_id = (int)$_GET['ID'];

		if (!$project_id) $project_id = (int)$_GET['ID'];
		$project = Ofit::getProjectByID($project_id);
		if ((int)$project['ID'] == self::$userID) return true;

		// query access_level
		$stmt = self::$pdo->prepare("SELECT access_level FROM access WHERE project_id = :pid AND user_id = :uid ");
		$stmt->bindParam(':pid', $project_id, PDO::PARAM_INT);
		$stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
		$stmt->execute();

		$result = $stmt->fetch();
		if (!$result) return false;
		if ( (int)$result['access_level'] > 4) 
		{
			if ($returnAccessLevel) 
			{
				return $result['access_level'];
			}
			return true; 
		}

		return false;
	}

	public static function insertComment($comment_text)
	{	
		$issue = Ofit::getIssueByID( (int)$_GET['ID'] );
		if (!Ofit::hasAccesTo( self::$userID, (int)$issue['project_id'])) return;

		$id = (int)$_GET['ID'];
		$date = date('Y-m-d H:I:s');

		$stmt = self::$pdo->prepare("INSERT INTO comments (`comment_text`, `user_id`, `issue_id`, `created_at`) VALUES (:t, :uid, :iid, :d) ");
		$stmt->bindParam(":t", $comment_text, PDO::PARAM_STR);
		$stmt->bindParam(":uid", self::$userID, PDO::PARAM_INT);
		$stmt->bindParam(":iid", $id, PDO::PARAM_INT);
		$stmt->bindParam(':d', $date, PDO::PARAM_STR);
		if ($stmt->execute()) return Ofit::Notice("Your comment has been added!" , "success");
		return Ofit::Notice('There was an error adding you comment', 'danger');
	}
}


Ofit::init();
?>
<!doctype html>
<html lang="en">
<head>	
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta charset="UTF-8">
	<title>OFit</title>
	<link rel="stylesheet" href="http://bootswatch.com/<?php echo OFIT_THEME; ?>/bootstrap.min.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script src="http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
	<style>.panel.panel-primary a {color:white;}</style>
</head>
<body>

	<div class="navbar navbar-default navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <a class="navbar-brand" href="<?php echo Ofit::$script; ?>?">OFit</a>
          <button data-target="#navbar-main" data-toggle="collapse" type="button" class="navbar-toggle">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
        </div>
        <div id="navbar-main" class="navbar-collapse collapse">

        <?php if (Ofit::$isLoggedIn) { ?>
          <ul class="nav navbar-nav">
            <li class="dropdown">
              <a id="projects" href="#" data-toggle="dropdown" class="dropdown-toggle">Projects <span class="caret"></span></a>
              <ul aria-labelledby="projects" class="dropdown-menu">   
               	<li><a href="<?php echo Ofit::$script; ?>">View All</a></li>
                <li class="divider"></li>
                <li><a href="<?php echo Ofit::$script; ?>?on=projects&amp;action=new">Create</a></li>
              </ul>
            </li>
            <li>
              <a data-toggle="dropdown" id="users" href="#">Users <span class="caret"></span></a>
              <ul class='dropdown-menu' aria-labelledby="users">
				<li><a href="<?php echo Ofit::$script; ?>?on=users&amp;action=list">View All</a></li>
				<li><a href="<?php echo Ofit::$script; ?>?on=users&amp;action=new">Add New</a></li>
              </ul>
            </li>
           
          </ul>
	
		<?php } else { ?>
				<?php if (Ofit::$registrationOpen) { ?>
				 <ul class="nav navbar-nav">
				 		<li><a href="<?php echo Ofit::$script; ?>?on=users&amp;action=register">Register</a></li>
				 </ul>
				<?php } ?>

		<?php } ?>

          <ul class="nav navbar-nav navbar-right">
            <li><a href="#"> Version <span class="label label-info"><?php echo OFIT_VERSION; ?></span></a></li>
            <li><a title='GitHub' target="_blank" href="http://github.com/Zenger/OFit">About</a></li>
          </ul>



        </div>

      </div>
    </div>
	
	<div class="clearfix"></div>
	<br><br>
	

	
    <div class='container'>
	
		<?php Ofit::Notice(); ?>

    	<?php 
    		


    		if (Ofit::$isPageLocked)
    		{
    			
    			?>

    			<h1 class="page-header">Login</h1>
				
						<form action="" method="post" class="form-horizontal">
							<div class="form-group">
								<label for="post_email" class="col-lg-2 control-label">E-mail</label>
								<div class="col-lg-10">
									<input type="email" class='form-control' <?php Ofit::post_value('post_email'); ?> id="post_email" name="post_email" required='required' />
								</div>
							</div>

							<div class="form-group">
								<label for="post_password" class="col-lg-2 control-label">Password</label>
								<div class="col-lg-10">
									<input type="password" class='form-control' id="post_password"  name="post_password" required='required' placeholder='' />
								</div>
							</div>

							<div class="form-group">
								<label for="post_password" class="col-lg-2 control-label">&nbsp;</label>
								<div class="col-lg-10">
									<input type="submit" value='Log in' class='btn btn-default' />
								</div>
							</div>

							<input type="hidden" name="action" value="users_login" />
						</form>
				
    			<?php
    			
    		}
    		else
    		{



    		if (Ofit::$on)
    		{

    			if (Ofit::$on == "users")
    			{
    				if (Ofit::$action == "new" || Ofit::$action == "edit")
    				{
    					?>
    						<?php 
	    						if (Ofit::$action == "new") 
	    						{ 
	    							echo '<h1 class="page-header">Add New User</h1>';
	    						}
    							else 
    							{
    								echo '<h1 class="page-header">Edit User</h1>';
    								$user = Ofit::getUserByID( (int)$_GET['ID'] );
    								$_POST['post_name']  = $user['user_name'];
    								$_POST['post_email'] = $user['user_email'];


    							}


    						?>
							<form class='form-horizontal' action="" method="post">
								<div class="form-group">
									<label class='col-lg-2 control-label' for="post_name">Name *</label>
									<div class="col-lg-10">
										<input type="text" id="post_name" required="required" <?php Ofit::post_value('post_name'); ?> name="post_name" class='form-control'>
									</div>
								</div>

								<div class="form-group">
									<label class='col-lg-2 control-label' for="post_email">E-mail *</label>
									<div class="col-lg-10">
										<input type="email" id="post_email" required="required" <?php Ofit::post_value('post_email'); ?>  name="post_email" class='form-control'>
									</div>
								</div>
								<?php if (Ofit::$action == "new") { ?>
								<div class="form-group">
									<label class='col-lg-2 control-label' for="post_password">Password *</label>
									<div class="col-lg-10">
										<input type="password" id="post_password" required="required"  name="post_password" class='form-control'>
									</div>
								</div>

								<div class="form-group">
									<label class='col-lg-2 control-label' for="post_password2">Repeat</label>
									<div class="col-lg-10">
										<input type="password" id="post_password2" required="required"  name="post_password2" class='form-control'>
									</div>
								</div>
								<?php  } ?>
								<div class="form-group">
									<label class='col-lg-2 control-label' for="re"></label>
									<div class="col-lg-10">
										<input type="submit" id="re" value='Submit' name="post_password" class='btn btn-default'>
									</div>
								</div>
								<?php if (Ofit::$action == "new") { ?>
								<input type="hidden" name="action" value='users_create' />
								<?php } else {  ?>
								<input type="hidden" name="action" value='users_update' />
								<input type='hidden' name='user_id' value='<?php echo $user['ID']; ?>' />
								<?php } ?>
							</form>
    					<?php
    				}
    				
    				if (Ofit::$action == "list")
    				{
    					?>
						<h1 class="page-header">All Users</h1>

						<table class="table table-striped table-hover ">
							<thead>
								<tr>
									<th>ID</th>
									<th>Name</th>
									<th>E-mail</th>
									<th>Action</th>
								</tr>
							</thead>

							<?php 
								$users =  Ofit::getUsers();

								
								if (!empty($users)) {
								foreach($users as $user) {
									?>
									<tr>
										<td><?php echo $user['ID']; ?></td>
										<td><a href="<?php echo Ofit::$script; ?>?on=users=&amp;action=view&amp;ID=<?php echo $user['ID']; ?>"><?php echo $user['user_name']; ?></td>
										<td><a href="mailto:<?php echo $user['user_email']; ?>"><?php echo $user['user_email']; ?></a></td>
										<td>
											<form onsubmit="return confirm('Are you sure you want to delete this user?');" action="" method="post">
												<input type="hidden" name="user_id" value='<?php echo $user['ID']; ?>' />
												<input type='hidden' name='action' value='users_delete' />
												<input type='submit' value='Delete' class='btn btn-xs btn-danger pull-left' />
											</form>
											<a href="<?php echo Ofit::$script; ?>?on=users&amp;action=edit&amp;ID=<?php echo $user['ID']; ?>" class='btn btn-success btn-xs pull-left col-sm-offset-1'>Edit</a>
										</td>
									</tr>
									<?php
								}
							} else {
								echo '<tr><td colspan="4"><div class="alert alert-warning">No users have been created!</div></td></tr>';
							}
							?>
						</table>
    					<?php
    				}


    				if (Ofit::$action == "view")
    				{
    					$user = Ofit::getUserByID( (int)$_GET['ID'] );
    					$issues = Ofit::getIssuesByUser( (int)$_GET['ID'] );
    					$comments = Ofit::getCommentsByUser( (int)$_GET['ID'] );
    					?>
							<h1 class="page-header"><?php echo $user['user_name']; ?></h1>
							<div class='alert alert-info'>Issues</div>

							
								<?php 
									if (!empty($issues)) {
										foreach ($issues as $issue) {
											echo "<div class='row'><div class='col-md-1'>#".$issue['ID']."</div><div><a href='".Ofit::$script."?on=issues&action=view&ID=".$issue['ID']."'>".$issue['issue_name']."</a></div></div>";
										}
									}
									else
									{
										echo "<div class='alert alert-danger'>No issues open by this user!</div>";
									}
								?>

								<div class="clearfix">&nbsp;</div>
							<div class='alert alert-info'>Comments</div>
								<?php 
									if (!empty($comments)) {
										foreach ($comments as $comment) {
											echo "<div class='row'><div class='col-md-1'>#".$comment['ID']."</div><div><a href='".Ofit::$script."?on=issues&action=view&ID=".$comment['issue_id']."'>".Ofit::cutWords( $comment['comment_text'], 10 )."</a></div></div>";
										}
									}
									else
									{
										echo "<div class='alert alert-danger'>No comments created by this user!</div>";
									}
								?>
							
    					<?php
    				}

    				if (Ofit::$action == "register")
    				{
    					?>
						<h1 class="page-header">Register</h1>
								<form action="" method="post" class="form-horizontal">
										<div class="form-group">
			      								<label for="" class="col-lg-2 control-label">Your Name*</label>
			      								<div class="col-lg-10">
			      									<input type="text" class='form-control' <?php Ofit::post_value('post_name'); ?> required='required' name="post_name" />
			      								</div>
			      						</div>

			      						<div class="form-group">
			      								<label for="" class="col-lg-2 control-label">E-mail*</label>
			      								<div class="col-lg-10">
			      									<input type="email" class='form-control' <?php Ofit::post_value('post_email'); ?> required='required' name="post_email" />
			      								</div>
			      						</div>

			      						<div class="form-group">
			      								<label for="" class="col-lg-2 control-label">Password*</label>
			      								<div class="col-lg-10">
			      									<input type="password" class='form-control' required='required' name="post_password" />
			      								</div>
			      						</div>
			      						<div class="form-group">
			      								<label for="" class="col-lg-2 control-label">Repeat*</label>
			      								<div class="col-lg-10">
			      									<input type="password" class='form-control' required='required' name="post_password2" />
			      								</div>
			      						</div>
			      						<div class="form-group">
			      								<label for="" class="col-lg-2 control-label">&nbsp;</label>
			      								<div class="col-lg-10">
			      									<input type="submit" class='btn btn-primary' value="Register" />
			      								</div>
			      						</div>

			      						<input type="hidden" name="action" value='users_register' />
								</form>
    					<?php
    				}
    			}


    			if (Ofit::$on == "projects")
    			{

    				if (Ofit::$action == "view")
    				{
    					$project = Ofit::getProjectByID((int)$_GET['ID']);
    					$labels  = Ofit::getLabels( (int)$_GET['ID'] );

    					$label = (isset($_GET['label'])) ? (int)$_GET['label'] : false;
    					$state = (isset($_GET['state'])) ? (int)$_GET['state'] : false;
    				
    					$issues = Ofit::getIssues( (int)$_GET['ID'],  $label, $state );
    					
    					

    					?>
    						<div class="row">
    							<div class="col-md-10"><h1 class="page-header">Issues</h1></div>
    							<div class="col-md-2"><button style='margin-top:50px;' data-toggle='modal' data-target='#add-new-issue' class='btn btn-success'>Add Issue</button></div>
    						</div>
							<div class="row">
								<div class="col-md-3">
									
									<div class="list-group">
										<span class="list-group-item"><h4 class='pull-left'>Labels</h4> <div class='pull-right'><a href='#' data-toggle="modal" data-target="#add-new-label" class='btn btn-xs btn-info'>Add</a></div> <div class="clearfix">	</div></span>
										<a class="list-group-item <?php if (!isset($_GET['label'])) { echo "active"; }?>" href="<?php echo Ofit::$script; ?>?on=projects&amp;action=view&amp;ID=<?php echo $project['ID']; ?>">All</a>
										<?php 
											if ($labels) 
											{
												foreach($labels as $label) {
													$label_class = (isset($_GET['label']) && $_GET['label'] == $label['ID']) ? "active" : "";
													echo '<a href="'. Ofit::$script . '?on=projects&amp;action=view&amp;ID=' . $project['ID'] . '&amp;label='.$label['ID'].'" class="list-group-item '.$label_class.'">'.$label['label_name'].'</a>';

												}
											}
										?>
									</div>
								</div>
								<div class="col-md-9">
									
									<div class="row">
										<div class="col-md-2">
											<div class="btn-group">
											  <a href="<?php echo Ofit::addUrlArg( 'state' , '1'); ?>" class="btn btn-xs btn-default">Open</a>
											  <a href="<?php echo Ofit::addUrlArg( 'state',  '0'); ?>" class="btn btn-xs btn-default">Closed</a>
											</div>
										</div>

										<div class="col-md-2">
											<div class='btn btn-warning disabled btn-xs'>@TODO: Sort By</div>
										</div>

										<div class="col-md-8">
											<div class='btn  btn-warning disabled  btn-xs'>@TODO: Pagination</div>
										</div>
									</div>

									<div class="clearfix">&nbsp;</div>

									<table class='table table-striped table-hover'>
										<tbody>
											<?php if (!empty($issues) )
											{
												foreach($issues as $issue) {
													echo "<tr><td><a href='".Ofit::$script."?on=issues&amp;action=view&amp;ID=".$issue['ID']."'> ".$issue['issue_name']."</a> </td><td>#".$issue['ID']."</td></tr>";
												}
											}
											else
											{
												echo "<tr><td colspan='3'> <div class='alert alert-warning'>No issues have been found</div></td></tr>";
											}
											?>
										</tbody>
									</table>

								</div>
							</div>
    					<?php
    				}

    				if (Ofit::$action == "new")
    				{
    						?>
								<h1 class="page-header">Create Project</h1>
								<form action="" method="post" class="form-horizontal">
										<div class="form-group">
			      								<label for="" class="col-lg-2 control-label">Project Name</label>
			      								<div class="col-lg-10">
			      									<input type="text" class='form-control' required='required' name="post_project_name" />
			      								</div>
			      						</div>
			      						<div class="form-group">
			      								<label for="" class="col-lg-2 control-label">&nbsp;</label>
			      								<div class="col-lg-10">
			      									<input type="submit" class='btn btn-primary' value="Create" />
			      								</div>
			      								<input type="hidden" name="action" value='projects_create' />
			      						</div>
								</form>
    						<?php
    				}
    			} // on projects

    			if (Ofit::$on == "issues")
    			{
    				if (Ofit::$action == "view")
    				{
    					$issue = Ofit::getIssueByID( (int)$_GET['ID'] );
    					$comments = Ofit::getComments( (int)$issue['ID'] );
    					$user = Ofit::getUserByID( (int)$issue['user_id'] );



    					?>
						<div class="row">
							<div class="col-md-9">
								<h2 class="page-header"><?php echo $issue['issue_name']; ?></h2>

							</div>

							

							<div class="col-md-1 ">
								<h4 class='page-header' style='margin-top:57px'>
									
									<?php if ($issue['issue_state'] == "1") { ?>
										<span class='label label-success'>Open</span>
									<?php } else { ?>
										<span class='label label-danger'>Closed</span>
									<?php } ?>
									</h4>
							</div>

							<div class="col-md-2 ">
								<h4 class='page-header' style='margin-top:57px'>

									<?php $labels = Ofit::getLabels( (int)$issue['project_id'] ); ?>
									<select>
										<option>None</option>
										<?php foreach($labels as $label) { ?>
											<option <?php Ofit::selected($issue['label_id'], $label['ID'], true); ?> value='<?php echo $label['ID']; ?>'><?php echo $label['label_name']; ?></option>
										<?php } ?>
									</select>
								</h4>
							</div>
							
						</div>

						<div class="row">
							<div class="col-md-1 avatar">
								<a href="<?php echo Ofit::$script; ?>?on=users&amp;action=view&amp;ID=<?php echo $user['ID']; ?>"> <?php Ofit::getUserAvatar( (int)$user['ID']  ); ?></a>
							</div>
							<div class="col-md-11">
								<div id="issue-<?php echo $issue['ID']; ?>" class="panel panel-default">
								  <div class="panel-heading"><a href="<?php echo Ofit::$script; ?>?on=users&amp;action=view&amp;ID=<?php echo $user['ID']; ?>"><?php echo $user['user_name']; ?></a> said this <?php echo Ofit::ago($issue['created_at']); ?></div>
								  <div class="panel-body">
								    <?php echo Ofit::_markdown( $issue['issue_text'] ); ?>
								  </div>
								</div>
							</div>
						</div>

						<?php 
							$_cclass = "primary"; // create odd like 
							if (!empty($comments))
							{
								foreach($comments as $comment)
								{
									$u = Ofit::getUserByID( (int)$comment['user_id'] );
									?>
										<div class="row">
											<div class="col-md-1 avatar">
												<a href="<?php echo Ofit::$script; ?>?on=users&amp;action=view&amp;ID=<?php echo $u['ID']; ?>"> <?php Ofit::getUserAvatar( (int)$comment['user_id']  ); ?></a>
											</div>
											<div class="col-md-11">
												<div id="comment-<?php echo $comment['ID']; ?>" class="panel panel-<?php echo $_cclass; ?>">
												  <div class="panel-heading"><a href="<?php echo Ofit::$script; ?>?on=users&amp;action=view&amp;ID=<?php echo $u['ID']; ?>"><?php echo $u['user_name']; ?></a> said this <?php echo Ofit::ago($comment['created_at']); ?></div>
												  <div class="panel-body">
												    <?php echo Ofit::_markdown( $comment['comment_text'] ); ?>
												  </div>
												</div>
											</div>
										</div>
									<?php

									$_cclass = ($_cclass == "primary") ? "default" : "primary";
								}
							}
						?>

						<?php if ( (int)$issue['issue_state'] == 0) { ?>
							<div class="alert alert-danger page-header">This issue has been closed. You can re-open it anytime.</div>
						<?php } ?>
		
						<h4 class="page-header">Leave Comment</h4>
						<div class="row">
							<div class="col-md-1 avatar">
								<a href="<?php echo Ofit::$script; ?>?on=users&amp;action=view&amp;ID=<?php echo $user['ID']; ?>"> <?php Ofit::getUserAvatar( (int)$user['ID']  ); ?></a>
							</div>
							<div class="col-md-11">
								<div class="panel panel-success">
								  <div class="panel-heading">Add your comment</div>
								  <div class="panel-body">
								  	<form action="" method="post">
								  		<textarea id='issueCommentText' name='comment_text' class='form-control' cols="30" rows="6" placeholder='Your Comment'></textarea>
								  		<input type='hidden' name='action' value='comments_create' />
								  		<input type='hidden' name='issue_id' value='<?php echo $issue['ID']; ?>' />
								  		<br>
								  		<input type='submit' class='btn btn-default pull-left' value='Comment' />
								  	</form>

								  	<form action="" onsubmit='jQuery("#issueCommentTextCC").val(jQuery("#issueCommentText").val());' method="post">
								  		<input type='hidden' id='issueCommentTextCC' name='comment_text' value='' />
								  		<input type='hidden' name='issue_id' value='<?php echo $issue['ID']; ?>' />
								  		<?php if ($issue['issue_state'] == 1) { ?>
								  		<input type='hidden' name='action' value='issues_close' />
								  		<input type='submit' title="Automatically posts your comment if there is any." class='btn btn-primary pull-left' style='margin-left:10px;' value='Close' />
									  	<?php } else { ?>
										<input type='hidden' name='action' value='issues_open' />
										<input type='submit' title="Automatically posts your comment if there is any." class='btn btn-primary pull-left' style='margin-left:10px;' value='Open' />
									  	<?php } ?>
								  		
								  	</form>
								  </div>
								</div>
							</div>
						</div>

    					<?php
    				}
    			} // on issues
    		}
    		else
    		{

    			$projects = Ofit::getProjects( (int)Ofit::$userID );

    			
    			?>
					<h1 class="page-header">Projects</h1>
					
					<table class="table table-striped table-hover">
					<thead>
						<tr>
							<th>ID</th>
							<th>Project Name</th>
							<th>Action</th>
						</tr>
					</thead>
					
					<?php if (!empty($projects)) { ?>
						<?php foreach($projects as $project) { ?>

						<tr>
							<td><?php echo $project['ID']; ?></td>
							<td><a href="<?php echo Ofit::$script; ?>?on=projects&amp;action=view&amp;ID=<?php echo $project['ID']; ?>"><?php echo $project['project_name']; ?></a></td>
							<td>
								<form onsubmit="return confirm('Are you sure you want to delete this project?');" action="" method="post">
									<input type="hidden" name="user_id" value='<?php echo $project['ID']; ?>' />
									<input type='hidden' name='action' value='projects_delete' />
									<input type='submit' value='Delete' class='btn btn-xs btn-danger pull-left' />
								</form>
								<a href="<?php echo Ofit::$script; ?>?on=projects&amp;action=edit&amp;ID=<?php echo $project['ID']; ?>" class='btn btn-success btn-xs pull-left col-sm-offset-1'>Edit</a>
							</td>
						</tr>
						
							
						<?php } ?>
					<?php } else { ?>
						<tr>
							<td colspan="3"><div class="alert alert-danger">No projects have been created!</div></td>
						</tr>
					<?php } ?>

					</table>
    			<?php
    		}

    	}// Is page locked
    	?>
    </div>


    <div class="modal fade" id="add-new-issue" tabindex="-1" role="dialog" aria-labelledby="newIssueLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="newIssueLabel">Add new issue</h4>
	      </div>
	      
	      <form class='form-horizontal' method='post' action=''>
	      <div class="modal-body">
	      	<div class="form-group">
	      		<label for="" class="col-lg-2 control-label">Issue</label>
	      		<div class="col-lg-10">
	      			<input type="text" class='form-control' required='required' name="post_issue_name" />
	      		</div>
	      	</div>

	      	<div class="form-group">
	      		<label for="" class="col-lg-2 control-label">Description</label>
	      		<div class="col-lg-10">
	      			<textarea name="post_issue_text" class='form-control' id="" cols="30" rows="5"></textarea>
	      		</div>
	      	</div>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	        <button type="submit" class="btn btn-primary">Create Issue</button>

	        <input type='hidden' name='action' value='issues_create' />
	      </div>
	    </form>
	    </div>
	  </div>
	</div>

	    <div class="modal fade" id="add-new-label" tabindex="-1" role="dialog" aria-labelledby="newIssueLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="newIssueLabel">Add new label</h4>
	      </div>
	      
	      <form class='form-horizontal' method='post' action=''>
	      <div class="modal-body">
	      	<div class="form-group">
	      		<label for="" class="col-lg-2 control-label">Label Name</label>
	      		<div class="col-lg-10">
	      			<input type="text" class='form-control' required='required' name="post_label" />
	      		</div>
	      	</div>

	   
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	        <button type="submit" class="btn btn-primary">Create</button>

	        <input type='hidden' name='action' value='labels_create' />
	      </div>
	    </form>
	    </div>
	  </div>
	</div>

</body>
</html>