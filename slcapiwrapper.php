<?

/* 
 * This is a wrapper for the Shared Learning Collaborative Data Store API.
 * For more information, please see http://dev.slcedu.org.
 * 
 * Requirements:  PHP 5 and user sessions must be enabled in PHP.ini.
 * 
 */


class slcAPIWrapper {

  public $clientID = 'B2v0Et8Puy';
  public $secret = 'i0JMOwV7NV7QNhm5fPpCOfjRRXC3Pi3l4Uk5xVo5YfpqOKIb';
  public $redirectURL = 'http://localhost/demo/main.php';
//  public $redirectURL = 'demo/main.html';
  
  public $endpointDomain = 'api.sandbox.slcedu.org';
  public $endpointAccessPath = '/api/rest/v1/';
  public $oAuthEndPntPath = '/api/oauth/';


  public function __construct() {
    if (!$this->clientID)
      die('Client ID not specified for SLC API.');
    elseif (!$this->secret)
      die('Secret not specified for SLC API.');
    elseif (!$this->redirectURL)
      die('Redirect URL not specified for SLC API.');
    
    if (!isset($_SESSION))
      session_start();
    if (isset($_SESSION['accessToken'])) {
      if (isset($_SESSION['lastAPICall']))
        $this->getSLCRes($_SESSION['lastAPICall']);
      return;
    }
    elseif (isset($_GET['code']))
      $this->getMyToken();
    else
      $this->authUser($func);
  }

  public function getStudent($studentID) {
    if (!$studentID)
      die('getStudent() method called without student ID.');
    return $this->getSLCRes("students/$studentID");
  }

  public function getAllStudents() {
    return $this->getSLCRes('students');
  }

  public function getAllStudentAssessments() {
    return $this->getSLCRes("studentAssessments");
  }

  public function getAllSections() {
    return $this->getSLCRes('sections');
  }

  public function getAttendances($studentID) {
    if (!$studentID)
      die('getAttendances() method called without student ID.');
    return $this->getSLCRes("attendances?studentId=$studentID");
  }

  public function getAllCourses() {
    return $this->getSLCRes('courses');
  }

  public function getAllReportCards() {
    return $this->getSLCRes('reportCards');
    return $jsonData;
  }

  public function getTeacher($teacherID) {
    if (!$teacherID)
      die('getTeacher() method called without teacher ID.');
    list($jsonData, $str) = $this->getSLCRes("teachers/$teacherID");
    return $jsonData;
  }

  public function getParents() {
    list($jsonData, $str) = $this->getSLCRes('parents');
    return $jsonData;
  }

  public function createStudentAssessment($assmntData) {
    if (!isset($assmntData))
      die('createStudentAssessment() method called without assessment data.');
    list($jsonData, $str) = $this->getSLCRes("studentAssessments", $assmntData);
    return $str;
  }

  private function getSLCRes($func, $postData = NULL) {
    if (isset($_SESSION['lastAPICall']))
      unset($_SESSION['lastAPICall']);
    if (isset($_SESSION['accessToken'])) {
      $apiRes = $this->getEndPntRes($this->endpointAccessPath, $func, $postData);
      $jsonData = json_decode($apiRes);
      if (isset($jsonData->type)) {
        if ($jsonData->type == 'Unauthorized')
          $this->authUser($func);
        elseif ($jsonData->type == 'Bad Request' || $jsonData->type == 'Forbidden')
          die("Call to '$func' failed.  $jsonData->message");
      }
      return array($jsonData, $apiRes);
    }
  }

  private function getMyToken() {
    if ($_SESSION['state'] == $_GET['state']) {
      $qryStr = "token?redirect_uri=$this->redirectURL&grant_type=authorization_code&client_id=$this->clientID&client_secret=$this->secret&code=$_GET[code]";
      $jsonData = json_decode($this->getEndPntRes($this->oAuthEndPntPath, $qryStr));
      $_SESSION['accessToken'] = $jsonData->access_token;
      echo $jsonData->access_token;
    }
    else
      die('Could not verify your authentication.  Please try again or contact customer support.');
  }
  
  private function getEndPntRes($endPntPath, $qryStr, $postData = NULL) {
    $host = "ssl://$this->endpointDomain";
    // open a socket connection on port 443 for HTTPS - timeout: 30 sec
    $fp = fsockopen($host, 443, $errno, $errstr, 30);
    if ($fp) {
      $resData = $hdrs = '';
      $resLines = array();
      $method = isset($postData) ? 'POST' : 'GET';
      $hdrs .= "$method $endPntPath$qryStr HTTP/1.1\r\n";
      $hdrs .= "Host: $host\r\n";
      $hdrs .= "Accept: application/json\r\n";
      $hdrs .= "Content-Type: application/json\r\n";
      if (isset($postData))
        $hdrs .= "Content-Length: " . strlen($postData) . "\r\n";
      if (isset($_SESSION['accessToken']))
        $hdrs .= "Authorization: bearer $_SESSION[accessToken]\r\n";
      $hdrs .= "Connection: close\r\n\r\n";
      fwrite($fp, $hdrs);
      if (isset($postData))
        fputs($fp, $postData);
      while (!feof($fp) && $line = trim(fgets($fp, 1024)))
        $resHdrs[] = $line;
      //print_r($resHdrs);
      while (!feof($fp))
        $resData .= fgets($fp, 1024);
      //while(!feof($fp))	echo fgets($fp);
      
      $chunked = false;
      for ($i=0; $i < count($resHdrs); $i++) {
        if ($resHdrs[$i] == 'transfer-encoding: chunked') $chunked = true;
      }
      
      if ($chunked) {
        $resData = $this->unchunkHttp($resData);
      }

      // echo "<pre>Headers sent: \r\n$hdrs \r\nHeaders Rec'd: \r\n" . implode("\r\n", $resHdrs) .  "\r\nResponse Data:$resData</pre>";
      return $resData;
    }
    else
      die("Error: $errstr ($errno)");
  }

  private function unchunkHttp($data) {
    $fp = 0;
    $outData = "";
    while ($fp < strlen($data)) {
      $rawnum = substr($data, $fp, strpos(substr($data, $fp), "\r\n") + 2);
      $num = hexdec(trim($rawnum));
      $fp += strlen($rawnum);
      $chunk = substr($data, $fp, $num);
      $outData .= $chunk;
      $fp += strlen($chunk);
    }
    return $outData;
  }
  
  private function authUser($lastFunc = NULL) {
    unset($_SESSION['accessToken']);
    $_SESSION['state'] = sha1(uniqid(rand(), TRUE)); // CSRF protection for OAuth
    $path = "https://$this->endpointDomain$this->oAuthEndPntPath" . "authorize?response_type=code&client_id=$this->clientID&redirect_uri=$this->redirectURL&state=$_SESSION[state]";
    $_SESSION['lastAPICall'] = $lastFunc;
    header("Location: $path");
    exit;
  }

}
function json_format($json) {
  $tab = "  ";
  $new_json = "";
  $indent_level = 0;
  $in_string = false;

  $json_obj = json_decode($json);

  if ($json_obj === false)
    return false;

  $json = json_encode($json_obj);
  $len = strlen($json);

  for ($c = 0; $c < $len; $c++) {
    $char = $json[$c];
    if ($char == '\\' && $c + 1 < $len && $json[$c + 1] != '\\')
      $char = '';

    switch ($char) {
      case '{':
      case '[':
        if (!$in_string) {
          $new_json .= $char . "\n" . str_repeat($tab, $indent_level + 1);
          $indent_level++;
        }
        else {
          $new_json .= $char;
        }
        break;
      case '}':
      case ']':
        if (!$in_string) {
          $indent_level--;
          $new_json .= "\n" . str_repeat($tab, $indent_level) . $char;
        }
        else {
          $new_json .= $char;
        }
        break;
      case ',':
        if (!$in_string) {
          $new_json .= ",\n" . str_repeat($tab, $indent_level);
        }
        else {
          $new_json .= $char;
        }
        break;
      case ':':
        if (!$in_string) {
          $new_json .= ": ";
        }
        else {
          $new_json .= $char;
        }
        break;
      case '"':
        if ($c > 0 && $json[$c - 1] != '\\') {
          $in_string = !$in_string;
        }
      default:
        $new_json .= $char;
        break;
    }
  }

  return $new_json;
}

?>