<?php
require_once('../lib/init.php');
$url = $_SERVER['HTTP_HOST'] . WEBDIR;
header('Content-type: application/xml');

echo <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<cartridge_basiclti_link xmlns="http://www.imsglobal.org/xsd/imslticc_v1p0"
    xmlns:blti = "http://www.imsglobal.org/xsd/imsbasiclti_v1p0"
    xmlns:lticm ="http://www.imsglobal.org/xsd/imslticm_v1p0"
    xmlns:lticp ="http://www.imsglobal.org/xsd/imslticp_v1p0"
    xmlns:xsi = "http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation = "http://www.imsglobal.org/xsd/imslticc_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticc_v1p0.xsd
    http://www.imsglobal.org/xsd/imsbasiclti_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imsbasiclti_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticm_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticm_v1p0.xsd
    http://www.imsglobal.org/xsd/imslticp_v1p0 http://www.imsglobal.org/xsd/lti/ltiv1p0/imslticp_v1p0.xsd">
    <blti:title>NCL CourseBuilder</blti:title>
    <blti:description>Direct students to CourseBuilder content.</blti:description>
    <blti:icon>https://${url}/images/coursebuilder_icon_128.png</blti:icon>
    <blti:launch_url>https://${url}/connect.php</blti:launch_url>
    <cartridge_bundle identifierref="BLTI001_Bundle"/>
    <cartridge_icon identifierref="BLTI001_Icon"/>
</cartridge_basiclti_link>
EOD;

?>
