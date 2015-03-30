<?php

require 'vendor/autoload.php';

$app = new \Slim\Slim();

$db = new SQLite3('db.sqlite3');

$app->group('/api/v1', function() use (&$app, &$db) {
  function performAuthentication($app, $db) {
    $username = $app->request->headers->get('Php-Auth-User');
    $password = $app->request->headers->get('Php-Auth-Pw');
    if ($username && $password) {
      $stmt = $db->prepare('SELECT * FROM users WHERE username = :username AND password = :password');
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

  $app->response->headers->set('Content-Type', 'application/json');

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

        array_walk($update_stmt, function(&$value, &$key) {
          $value = "$key=$value";
        });

        $stmt = $db->prepare('UPDATE users SET ' . implode(',',$update_stmt) . ' WHERE username=:old;');
        $stmt->bindValue(':old', $username, SQLITE3_TEXT);
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

  $app->delete('/users/:username', function($username) use (&$app, &$db) {
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
});


$app->run();

?>