<?php

class Tracking {

  private const URL = 'https://track.amazon.it/';
  private const API_PATH = 'api/tracker/';
  private const TRANSLATIONS = array(
    "swa_rex_detail_pickedUp" => "Pacco ritirato",
    "swa_rex_arrived_at_sort_center" => "Il pacco è arrivato presso la sede del corriere",
    "swa_rex_ofd" => "In consegna",
    "swa_rex_intransit" => "In transito",
    "swa_rex_detail_departed" => "Il pacco ha lasciato la sede del corriere",
    "swa_rex_detail_creation_confirmed" => "Etichetta creata",
    "swa_rex_shipping_label_created" => "Etichetta creata",
    "swa_rex_detail_arrived_at_delivery_Center" => "In consegna",
    "swa_rex_detail_delivered" => "Pacco consegnato"
  );
  private string $timezone;

  public function __construct(private string $trackingNumber) {
    if (ctype_alnum($trackingNumber)) {
      $html = $this->getUrlContent(SELF::URL);
      $this->timezone = $this->getTimezone($html);
    } else {
      throw new Exception("Errore - Il valore di trackingId non è valido!");
    }
  }

  private function getTimezone(string $html): string {
    $pattern = '/<input type="hidden" value="([^"]+)" name="timeZone"/';
    if (preg_match($pattern, $html, $matches)) {
      return $matches[1];
    } else {
      return 'GMT';
    }
  }

  private function getUrlContent(string $url): string|bool {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($httpCode === 200 and $result) {
      return $result;
    } else {
      throw new Exception("Errore - C'è stato un problema di sistema, riprovare più tardi");
    }
  }

  private function getEventHistory() {
    $result = $this->getUrlContent(self::URL . self::API_PATH . $this->trackingNumber);
    $data = json_decode($result, true);
    if (!empty($data['eventHistory'])) {
      return json_decode($data['eventHistory'], true)['eventHistory'];
    } else {
      throw new Exception('Errore - Non siamo riusciti a trovare il pacco che stai cercando!');
    }
  }

  private function convertDatetime(string $datetime): string {
    $gmtDateTime = new DateTime($datetime, new DateTimeZone('GMT'));
    if ($this->timezone !== 'GMT') {
      $newTimeZone = new DateTimeZone($this->timezone);
      $gmtDateTime->setTimezone($newTimeZone);
    }
    return $gmtDateTime->format('Y-m-d H:i:s');
  }

  private function translateStatus(array $event): string {
    $localisedStringId = $event['statusSummary']['localisedStringId'];
    if (isset(self::TRANSLATIONS[$localisedStringId])) {
      return self::TRANSLATIONS[$localisedStringId];
    } else {
      return $event['eventCode'];
    }
  }

  public function getTracking(): array {
    $data = array();
    $eventHistory = $this->getEventHistory();
    foreach ($eventHistory as $event) {
      $eventData = array(
        'status' => $this->translateStatus($event),
        'time' => $this->convertDatetime($event['eventTime'])
      );
      if (!empty($event['location'])) {
        $eventData['location'] = implode(' ', array_values($event['location']));
      }
      array_push($data, $eventData);
    }
    return $data;
  }
}
