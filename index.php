<?php

try {
  require_once('tracking.php');
  $response = array(
    'message' => 'Errore - Non è stato passato alcun trackingId'
  );
  if (!empty($_GET['trackingId'])) {
    $tracking = new Tracking($_GET['trackingId']);
    $response = $tracking->getTracking();
  }
} catch (Exception | Error $e) {
  $response = array(
    'message' => $e->getMessage()
  );
} finally {
  header("Content-Type: application/json; charset=UTF-8");
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
