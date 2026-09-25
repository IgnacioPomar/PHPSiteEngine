<?php

namespace PHPSiteEngine;

include_once ('UUIDv7.php');

class Migrator
{
	private $context;


	/**
	 * Constructor
	 */
	public function __construct ($context)
	{
		$this->context = $context;
	}


	public function migrate02To03 (): bool
	{
		// Load the plugin class at the beginning, so if any error, it will happen before the migration
		if (file_exists (Site::$plgsPath . 'migrators/Migrator02To03.php'))
		{
			require_once (Site::$plgsPath . 'migrators/Migrator02To03.php');
		}

		echo "<h1> Migrate 0.2 to 0.3</h1>";

		// --- In the table weUsers --
		// 1.- Create new equivalent field: uuidUser
		$sql = 'CREATE TABLE weMigration_02to03 ( oldIdUser INT, newIdUser VARCHAR(24));';
		if ($this->context->mysqli->query ($sql) === TRUE)
		{
			echo '<div class="ok">Creating equivalent codes:  <b>OK</b></div>';
		}
		else
		{
			echo '<div class="fail"><b>Error</b>: Unablle to create  equivalent codes.<br />: ' . $this->context->mysqli->error . '</div>';
			return FALSE;
		}

		// 2.- fill the user fiels with the old one
		$sql = 'INSERT INTO weMigration_02to03 (oldIdUser) SELECT idUser FROM weUsers;';
		if ($this->context->mysqli->query ($sql) === TRUE)
		{
			echo '<div class="ok">Filling equivalent codes:  <b>OK</b></div>';
		}
		else
		{
			echo '<div class="fail"><b>Error</b>: Unablle to fill equivalent codes.<br />: ' . $this->context->mysqli->error . '</div>';
			return FALSE;
		}

		// 3.- Create the new values
		{
			$sql = 'Select oldIdUser from weMigration_02to03 ORDER BY oldIdUser;';
			$result = $this->context->mysqli->query ($sql);
			if ($result)
			{
				$updateStmt = $this->context->mysqli->prepare ("UPDATE weMigration_02to03 SET newIdUser = ? WHERE oldIdUser = ?");

				while ($row = $result->fetch_assoc ())
				{
					$newIdUser = UUIDv7::generateBase64 ();
					$updateStmt->bind_param ("si", $newIdUser, $row ['oldIdUser']);
					$updateStmt->execute ();
				}
				$updateStmt->close ();
			}

			$result->close ();
		}

		// 4.- Add the new row on each table
		{
			$tables = [ 'wePermissionsUsers' => 'idUser', 'weUsers' => 'idUser', 'weUsersGroups' => 'idUser', 'weSessCookie' => 'realUserId'];
			foreach ($tables as $table => $field)
			{
				$DropIdx = $field == 'idUser' ? 'DROP PRIMARY KEY,' : '';
				$sql = "ALTER TABLE $table $DropIdx CHANGE COLUMN $field OldidUser  INT(11) NOT NULL, ADD COLUMN $field VARCHAR(24) AFTER OldidUser;";
				if ($this->context->mysqli->query ($sql) === TRUE)
				{
					echo "<div class=\"ok\">Adding uuidUser to $table:  <b>OK</b></div>";
				}
				else
				{
					echo "<div class=\"fail\"><b>Error</b>: Unablle to add uuidUser to $table.<br />$sql<br />: " . $this->context->mysqli->error . "</div>";
				}

				$sql = "UPDATE $table t, weMigration_02to03 m SET t.$field = m.newIdUser WHERE t.OldidUser = m.oldIdUser;";
				if ($this->context->mysqli->query ($sql) === TRUE)
				{
					echo "<div class=\"ok\">Filling uuidUser in $table: <b>OK</b></div>";
				}
				else
				{
					echo "<div class=\"fail\"><b>Error</b>: Unablle to fill uuidUser in $table.<br />: " . $this->context->mysqli->error . "</div>";
				}
			}

			// 5.- Delete now junk field OldidUser of each table
			foreach ($tables as $table => $field)
			{
				$sql = "ALTER TABLE $table DROP COLUMN OldidUser;";
				if ($this->context->mysqli->query ($sql) === TRUE)
				{
					echo "<div class=\"ok\">Deleting OldidUser from $table:  <b>OK</b></div>";
				}
				else
				{
					echo "<div class=\"fail\"><b>Error</b>: Unablle to delete OldidUser from $table.<br />: " . $this->context->mysqli->error . "</div>";
				}
			}
		}

		// 6.- Change the key
		{
			$keys = [ 'wePermissionsUsers' => [ 'mnuNode', 'plgName', 'idUser', 'permName'], 'weUsers' => [ 'idUser'], 'weUsersGroups' => [ 'idUser', 'idGrp']];
			foreach ($keys as $table => $fields)
			{
				$sql = "ALTER TABLE $table ADD PRIMARY KEY (" . implode (',', $fields) . ");";
				if ($this->context->mysqli->query ($sql) === TRUE)
				{
					echo "<div class=\"ok\">Changing primary key in $table:  <b>OK</b></div>";
				}
				else
				{
					echo "<div class=\"fail\"><b>Error</b>: Unablle to change primary key in $table.<br />: " . $this->context->mysqli->error . "</div>";
				}
			}
		}

		// --- finally, call the plugin migrators, if any
		$className = '\Migrator02To03';
		if (class_exists ($className))
		{
			$migrator = new $className ($this->context);
			return $migrator->migrate ();
		}
		else
		{
			return TRUE;
		}
	}


	/**
	 * Migrate the site to the new version
	 */
	public static function migrate ($context)
	{
		$migrator = new Migrator ($context);

		echo 'Migrating...';
		switch ($GLOBALS ['Version'])
		{
			case '0.2':
				$migrator->migrate02To03 ();
				break;
		}

		// Finally, edit the config file to match version
		$newContent = '';

		$handle = fopen (Site::$cfgFile, 'r');
		if ($handle)
		{
			while (($line = fgets ($handle)) !== false)
			{
				// check if the file contains $GLOBALS ['Version']
				if (preg_match ('/\$GLOBALS\s*\[\s*\x27Version\x27\s*\]/', $line))
				{
					$newContent .= '$GLOBALS [\'Version\'] = \'' . Site::VERSION . '\';' . PHP_EOL;
				}
				else
				{
					$newContent .= $line;
				}
			}
			fclose ($handle);
			file_put_contents (Site::$cfgFile, $newContent);
		}
		else
		{
			echo 'Error opening the config file';
		}
	}
}

abstract class PLuginMigrator
{
	protected Context $context;


	public function __construct (Context &$context)
	{
		$this->context = &$context;
		$this->uriPrefix = $context->mnu->getUriPrefix ();
	}


	abstract public function migrate ();
}

