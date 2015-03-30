<?php

require 'vendor/autoload.php';

$app = new \Slim\Slim();

$db = new SQLite3('db.sqlite3');

//Add group for version separation.
$app->group('/api/v1', function() use (&$app, &$db) {

  // Authentication function.
  // Will throw on unauthorized access.
  // All authorization is done using HTTP Basic Authentication.
  function performAuthentication($app, $db) {
    $username = $app->request->headers->get('Php-Auth-User');
    $password = $app->request->headers->get('Php-Auth-Pw');
    if ($username && $password) {
      $stmt = $db->prepare(
        'SELECT
          *
        FROM
          users
        WHERE
          username = :username AND password = :password'
      );

      $stmt->bindValue(':username', $username, SQLITE3_TEXT);
      $stmt->bindValue(':password', $password, SQLITE3_TEXT);
      $set = $stmt->execute();
      $row = $set->fetchArray(SQLITE3_NUM);
      $set->finalize();
      if ($row === false) {
        throw new Exception('Not authorized.');
      }
    } else {
      throw new Exception('No basic authentication used.');
    }
  }

  // Set default Content-Type.
  $app->response->headers->set('Content-Type', 'application/json');

  /* All methods follow the same general style. (Hmmm, method extraction maybe?)
   * A declaration of the default return value.
   * Enter try block.
   * An authentication check.
   * Some SQL based on the action tied to the route.
   * Some update of the return value based on query results. (Mostly not for POST based methods.)
   * Exit try block.
   * Obligatory catch block, which will update the error property.
   * Write JSON encoded return collection back to the client.
  */

  // Get all sent/received messages for the authorized user.
  $app->get('/messages', function() use (&$app, &$db) {
    $res = [
      'messages' => null,
      'error' => null
    ];

    try {
      performAuthentication($app, $db);

      $user = $app->request->headers->get('Php-Auth-User');

      $stmt = $db->prepare(
          'SELECT
            sen.username AS "from",
            (SELECT username FROM users WHERE id = to_id) AS "to",
            messages.message AS "message",
            strftime("%Y-%m-%dT%H:%M:%fZ", messages.timestamp) AS "timestamp"
          FROM
            (SELECT id, username FROM users WHERE username = :this_user) AS sen,
            messages
          WHERE
            messages.from_id = sen.id
          UNION
          SELECT
            (SELECT username FROM users WHERE id = from_id) AS "from",
            rec.username AS "to",
            messages.message AS "message",
            messages.timestamp AS "timestamp"
          FROM
            (SELECT id, username FROM users WHERE username = :this_user) AS rec,
            messages
          WHERE
            messages.to_id = rec.id
          ORDER BY
            timestamp ASC;'
      );

      $stmt->bindValue(':this_user', $user, SQLITE3_TEXT);
      $set = $stmt->execute();

      $res['messages'] = [];

      while (($row = $set->fetchArray(SQLITE3_ASSOC)) !== false) {
        var_dump($row['timestamp']);
        array_push($res['messages'], $row);
      }

      $set->finalize();
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });

  // Remove all sent messages for the authorized user.
  $app->delete('/messages', function() {
    $res = [
      'error' => null
    ];

    try {
      performAuthentication($app, $db);

      $stmt = $db->prepare(
        'DELETE FROM
          users
        WHERE
          username = :username;'
      );
      $stmt->bindValue(':username', $username, SQLITE3_TEXT);
      $stmt->execute()->finalize();
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });

  // Get all messages sent to the specified user for the authorized user.
  $app->get('/messages/:username', function($username) use (&$app, &$db) {
    $res = [
      'messages' => null,
      'error' => null
    ];

    try {
      performAuthentication($app, $db);

      $user = $app->request->headers->get('Php-Auth-User');

      $stmt = $db->prepare(
          'SELECT
            sen.username AS "from",
            rec.username AS "to",
            messages.message AS "message",
            strftime("%Y-%m-%dT%H:%M:%fZ", messages.timestamp) AS "timestamp"
          FROM
            (SELECT id, username FROM users WHERE username = :this_user) AS sen,
            (SELECT id, username FROM users WHERE username = :diff_user) AS rec,
            messages
          WHERE
            messages.from_id = sen.id AND messages.to_id = rec.id
        UNION
          SELECT
            sen.username AS "from",
            rec.username AS "to",
            messages.message AS "message",
            messages.timestamp AS "timestamp"
          FROM
            (SELECT id, username FROM users WHERE username = :diff_user) AS sen,
            (SELECT id, username FROM users WHERE username = :this_user) AS rec,
            messages
          WHERE
            messages.from_id = sen.id AND messages.to_id = rec.id
        ORDER BY
          timestamp ASC;'
      );

      $stmt->bindValue(':this_user', $user, SQLITE3_TEXT);
      $stmt->bindValue(':diff_user', $username, SQLITE3_TEXT);
      $set = $stmt->execute();

      $res['messages'] = [];

      while (($row = $set->fetchArray(SQLITE3_ASSOC)) !== false) {
        array_push($res['messages'], $row);
      }

      $set->finalize();
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });

  // Send a message to the specified user for the authorized user.
  $app->post('/messages/:username', function($username) use (&$app, &$db) {
    $res = [
      'error' => null
    ];

    try {
      performAuthentication($app, $db);

      $req = json_decode($app->request->getBody(), true);
      $user = $app->request->headers->get('Php-Auth-User');

      $stmt = $db->prepare(
        'INSERT INTO
          messages (from_id, to_id, message)
        VALUES (
          (SELECT users.id FROM users WHERE users.username = :this_user),
          (SELECT users.id FROM users WHERE users.username = :diff_user),
          :message
        );'
      );
      $stmt->bindValue(':this_user', $user);
      $stmt->bindValue(':diff_user', $username);
      $stmt->bindValue(':message', $req['message']);
      $stmt->execute()->finalize();
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });

  // Remove all messages sent to the specified user for the authorized user.
  $app->delete('/messages/:username', function($username) use (&$app, &$db) {
    $res = [
      "error" => null
    ];

    try {
      performAuthentication($app, $db);

      $user = $app->request->headers->get('Php-Auth-User');

      $stmt = $db->prepare(
        'DELETE FROM
          messages
        WHERE
          from_id = :this_user AND to_id = :diff_user;'
      );
      $stmt->bindValue(':this_user', $user, SQLITE3_TEXT);
      $stmt->bindValue(':diff_user', $username, SQLITE3_TEXT);
      $stmt->execute()->finalize();
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });

  // Create a new user, given the specified username and password in the JSON formatted POST data.
  $app->post('/users', function() use (&$app, &$db) {
    $res = [
      "error" => null
    ];

    try {
      $req = json_decode($app->request->getBody(), true);
      $stmt = $db->prepare(
        'INSERT INTO
          users (username, password)
        VALUES (:username, :password);'
      );
      $stmt->bindValue(':username', $req['username'], SQLITE3_TEXT);
      $stmt->bindValue(':password', $req['password'], SQLITE3_TEXT);
      $stmt->execute()->finalize();
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });

  // Update the specified user for the authorized user.
  // Currently only accepts the modification if both users are the same.
  // Could be extended in the database to support user management.
  $app->post('/users/:username', function($username) use (&$app, &$db) {
    $params = [
      "username" => ":username",
      "password" => ":password"
    ];
    $res = [
      "error" => null
    ];

    try {
      performAuthentication($app, $db);

      $user = $app->request->headers->get('Php-Auth-User');

      if ($user === $username) {
        $update_params = array_intersect_key($params, $req);
        $update_stmt = $update_params;
        $update_values = array_intersect_key($req, $update_params);

        // Attention! This doesn't concat the actual keys/values, it concats the param binding.
        array_walk($update_stmt, function(&$value, &$key) {
          $value = "$key=$value";
        });

        $stmt = $db->prepare(
          'UPDATE
            users
          SET ' . implode(',',$update_stmt) . 'WHERE
            username = :old;'
        );
        $stmt->bindValue(':old', $username, SQLITE3_TEXT);

        // Actual binding of the parameters is done here.
        foreach ($update_params as $key => $value) {
          $stmt->bindValue($value, $update_values[$key], SQLITE3_TEXT);
        }
        $stmt->execute()->finalize();
      } else {
        throw new Exception('Not authorized for this combination of users.');
      }
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });

  // Remove the specified user for the authorized user.
  // Same user management features as for updating.
  // Will also remove all sent/received messages for this user.
  $app->delete('/users/:username', function($username) use (&$app, &$db) {
    $res = [
      'error' => null
    ];

    try {
      performAuthentication($app, $db);

      $user = $app->request->headers->get('Php-Auth-User');

      if ($user === $username) {
        $stmt = $db->prepare(
          'DELETE FROM
            users
          WHERE
            username = :username;'
        );
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->execute()->finalize();
      } else {
        throw new Exception('Not authorized for this combination of users.');
      }
    } catch (Exception $e) {
      $app->response->setStatus(400);
      $res['error'] = $e->getMessage();
    }

    echo json_encode($res);
  });
});

// RUN, RUN, RUDOLF!
$app->run();

?>