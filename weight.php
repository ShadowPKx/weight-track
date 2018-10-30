<?php

/*
 * When calleld as is, return JSON object like this:
 * {
 *   "users": [
 *     {
 *       "name": "User 1",
 *       "id": 0,
 *       "data": [
 *         {
 *           "x": "2018-10-29 20:30",
 *           "y": "110.0"
 *         },
 *         {
 *           "x": "2018-10-30 20:30",
 *           "y": "110.5"
 *         }
 *       ]
 *     },
 *     {
 *       "name": "User 2",
 *       "id": 1,
 *       "data": [
 *         {
 *           "x": "2018-10-29 20:30",
 *           "y": "105.5"
 *         },
 *         {
 *           "x": "2018-10-30 20:30",
 *           "y": "105.0"
 *         }
 *       ]
 *     }
 *   ]
 * }
 * or
 * {
 *   "error": "No configuration found"
 * }
 *
 * When called with POST parameters, add user or data-point to db
 * parameters: username, (date, time, weight)
 */

function print_json_error($s) {
    echo '{';
    echo '  "error": "' . $s;
    if (mysql_errno()) {
        echo ' (' . mysql_error() . ')';
    }
    echo '"';
    echo '}';
}

function print_html($s) {
    echo '<html lang="en">';
    echo '    <head>';
    echo '        <meta charset="utf-8" />';
    echo '        <title>Weight-Track DB</title>';
    echo '    </head>';
    echo '    <body>';
    echo '        <p>' . $s . '</p>';
    if (mysql_errno()) {
        echo '<p>MySQL Error: "' . mysql_error() . '"</p>';
    }
    echo '        <a href="index.html">Back to Main-Page...</a>';
    echo '    </body>';
    echo '</html>';
}

function print_error($s) {
    if (isset($_POST['username'])) {
        return print_html($s);
    } else {
        return print_json_error($s);
    }
}

include('config.php');

if ((!isset($sql_host))
        || (!isset($sql_username))
        || (!isset($sql_password))
        || (!isset($sql_database))) {
    print_error('Configuration Error');
    exit(1);
}

$db = mysql_connect($sql_host, $sql_username, $sql_password);
mysql_select_db($sql_database);
if (mysql_errno()) {
    print_error('Database Error');
    exit(1);
}

$sql = 'CREATE TABLE IF NOT EXISTS weight_users (';
$sql .= 'id int NOT NULL AUTO_INCREMENT,';
$sql .= 'name varchar(255),';
$sql .= 'PRIMARY KEY (id)';
$sql .= ');';
$result = mysql_query($sql);
if (!$result) {
    print_error('Error (re-) creating database table for users!');
    exit(1);
}

$sql = 'CREATE TABLE IF NOT EXISTS weight_data (';
$sql .= 'id int NOT NULL,';
$sql .= 'date DATETIME,';
$sql .= 'weight DECIMAL(5,2)';
$sql .= ');';
$result = mysql_query($sql);
if (!$result) {
    print_error('Error (re-) creating database table for weights!');
    exit(1);
}

if (isset($_POST['username'])
        && isset($_POST['date'])
        && isset($_POST['time'])
        && isset($_POST['weight'])) {
    $sql = 'INSERT INTO weight_data(id, date, weight) VALUES (';
    $sql .= mysql_real_escape_string(str_replace('user_', '', $_POST['username']));
    $sql .= ', ';

    $datetime = $_POST['date'] . $_POST['time'];
    $timestamp = strtotime($datetime);
    if (($timestamp == FALSE) || ($timestamp == -1)) {
        print_error('Error interpreting DateTime: "' . $datetime . '"');
        exit(1);
    }
    $mysqltime = date("Y-m-d H:i:s", $timestamp);
    $sql .= '"' . $mysqltime . '", ';

    $sql .= mysql_real_escape_string($_POST['weight']) . ')';

    $result = mysql_query($sql);
    if (!$result) {
        print_error('Error adding new data for user "' . $_POST['username'] . '" to DB! ' . $sql);
    } else {
        print_error('Added new data for user "' . $_POST['username'] . '" to DB!');
    }
} else if (isset($_POST['username'])) {
    $sql = 'INSERT INTO weight_users(name) VALUES ("';
    $sql .= mysql_real_escape_string($_POST['username']);
    $sql .= '")';
    $result = mysql_query($sql);
    if (!$result) {
        print_error('Error adding new user "' . $_POST['username'] . '" to DB!');
    } else {
        print_error('Added new user "' . $_POST['username'] . '" to DB!');
    }
} else if (isset($_GET['debug'])) {
    echo <<<EOF
{
  "users": [
    {
      "name": "User 1",
      "id": 0,
      "data": [
        {
          "x": "2018-10-27 20:30",
          "y": "110.0"
        },
        {
          "x": "2018-10-28 20:30",
          "y": "110.7"
        },
        {
          "x": "2018-10-29 20:30",
          "y": "110.2"
        },
        {
          "x": "2018-10-30 20:30",
          "y": "111.8"
        }
      ]
    },
    {
      "name": "User 2",
      "id": 1,
      "data": [
        {
          "x": "2018-10-27 20:30",
          "y": "103.5"
        },
        {
          "x": "2018-10-28 20:30",
          "y": "105.0"
        },
        {
          "x": "2018-10-29 20:30",
          "y": "105.5"
        },
        {
          "x": "2018-10-30 20:30",
          "y": "104.0"
        }
      ]
    }
  ]
}
EOF;
} else {
    $sql = 'SELECT id, name FROM weight_users ORDER BY id ASC';
    $result = mysql_query($sql);
    if (!$result) {
        print_error('Error fetching users from database!');
        exit(1);
    }
    $data = array();
    while ($row = mysql_fetch_array($result)) {
        $sql2 = 'SELECT date, weight FROM weight_data ';
        $sql2 .= 'WHERE id = "' . $row['id'] . '" ORDER BY date ASC';
        $result2 = mysql_query($sql2);
        if (!$result2) {
            print_error('Error fetching data for user ' . $row['id'] . ' "' . $row['name'] . '"!');
            exit(1);
        }
        $cur = array();
        $cur['name'] = $row['name'];
        $cur['id'] = $row['id'];
        $cur['data'] = array();
        while ($row2 = mysql_fetch_array($result2)) {
            $elem = array();
            $elem['date'] = $row2['date'];
            $elem['weight'] = $row2['weight'];
            $cur['data'][] = $elem;
        }
        $data[] = $cur;
    }
    echo '{"users": [';
    foreach ($data as $data_key => $data_value) {
        if ($data_key > 0) {
            echo ',';
        }
        echo '{';
        echo '"name": "' . $data_value['name'] . '",';
        echo '"id": ' . $data_value['id'] . ',';
        echo '"data": [';
        foreach ($data_value['data'] as $row_key => $row_value) {
            if ($row_key > 0) {
                echo ',';
            }
            echo '{';
            echo '"x": "' . $row_value['date'] . '",';
            echo '"y": "' . $row_value['weight'] . '"';
            echo '}';
        }
        echo ']}';
    }
    echo ']}';
}

?>
