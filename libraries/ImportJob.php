<?php

class CollectionSpaceImport_ImportJob extends Omeka_Job_AbstractJob
{

    private $dcMap = array(
        'title' => 'Title',
        'objectNumber' => 'Identifier',
        'briefDescription' => 'Description',
        'contentLanguage' => 'Language',
        'objectProductionDate' => 'Date',
        'objectName' => 'Type',
        'contentConcept' => 'Subject',
        'dimension' => 'Format',
        'objectProductionPerson' => 'Creator'
    );

    private $additionalCollectionObjectFieldsMap = array();

    private $personMap = array(

    );

    private $organizationMap = array(

    );

    public function perform()
    {
        $imageFileData = array();
        $collectionObjects = $this->getCollectionObjectsWithImages();
        $dataForOmeka = $this->mapCollectionSpaceDataToOmeka($collectionObjects);

        //submit $dataForOmeka and $imageFileData to create new records
        // bind the collectionObject csid to the import record
    }

    function getCollectionObjectsWithImages()
    {
        $params = array(
            array("sbjType", "CollectionObject"),
            array("objType", "Media")
        );

        $client = $this->buildCollectionSpaceRequest("relations", $params );
        $payload = $client->request();
        return simplexml_load_string($payload);
    }

    function mapCollectionSpaceDataToOmeka(SimpleXMLElement $collectionObjects)
    {
        $data = array();
        foreach ($collectionObjects as $co) {
            $data[] = $this->mapCollectionObjectFieldsToOmeka($co);
        }
        return $data;
    }

    private function mapCollectionObjectFieldsToOmeka($co)
    {
        $objectRecord = $this->getCollectionObjectPayload($co->subjectCsid);
        $dc = $this->mapCollectionObjectFieldsToDC($objectRecord);
        $additional = $this->mapAdditionalFields($objectRecord);
        return array(
            "Dublin Core" => $dc,
            "Additional"  => $additional
        );
    }

    function mapCollectionObjectFieldsToDC(SimpleXMLElement $record)
    {
        $dc = array();
        foreach ($this->dcMap as $cspaceField => $dcTerm){
            if (isset($record->$cspaceField)) {
                $dc[$dcTerm] = array(
                    array( "text" => $record->$cspaceField )
                );
            }
        }
        return $dc;
    }


    function mapAdditionalFields(SimpleXMLElement $collectionObject) {

        $additionalObjectFields = $this->mapAdditionalCollectionObjectFields($collectionObject);
        $personFields = $this->mapPersonFields($collectionObject);
        $organizationFields = $this->mapOrganizationFields($collectionObject);
        return array_merge($additionalObjectFields, $personFields, $organizationFields);

    }

    private function mapPersonFields(SimpleXMLElement $collectionObject)
    {
        $personFields = array();
        if (isset($collectionObject->objectProductionPerson)){
            $person = $this->getPersonPayload($collectionObject->objectProductionPerson);
            foreach ($this->personMap as $cspaceField => $newOmekaField) {
                if (isset($person->$cspaceField)) {
                    $personFields[$newOmekaField] = array ('text' => $person->$cspaceField);
                }
            }

        }

        return $personFields;
    }

    private function mapOrganizationFields($collectionObject)
    {
        $organizationFields = array();
        if (isset($collectionObject->objectProductionOrganization)){
            $org = $this->getOrganizationPayload($collectionObject->objectProductionOrganization);
            foreach ($this->personMap as $cspaceField => $newOmekaField) {
                if (isset($org->$cspaceField)) {
                    $personFields[$newOmekaField] = array ('text' => $org->$cspaceField);
                }
            }

        }
        return $organizationFields;
    }

    private function mapAdditionalCollectionObjectFields($collectionObject)
    {
        $additional = array();
        foreach ($this->additionalCollectionObjectFieldsMap as $cspaceField => $newOmekaField) {
            if (isset($collectionObject->$cspaceField)) {
                $personFields[$newOmekaField] = array('text' => $collectionObject->$cspaceField);
            }
        }
        return $additional;
    }

    function getCollectionObjectPayload($csid){
        $client = $this->buildCollectionSpaceRequest("collectionobjects/" . $csid);
        $payload = $client->request();
        return simplexml_load_string($payload);

    }

    private function getPersonPayload($objectProductionPerson)
    {
        $csid = $objectProductionPerson->csid;
        $client = $this->buildCollectionSpaceRequest("collectionobjects/" . $csid);
        $payload = $client->request();
        return simplexml_load_string($payload);
    }

    private function getOrganizationPayload($objectProductionOrganization)
    {
        $csid = $objectProductionOrganization->csid;
        $client = $this->buildCollectionSpaceRequest("collectionobjects/" . $csid);
        $payload = $client->request();
        return simplexml_load_string($payload);
    }


    function buildCollectionSpaceRequest($path, Array $params=array()) {
        $client = new Zend_HTTP_CLIENT();
        $auth = $this->getAuthorization();
        $client->setUri($this->getBaseServiceUrl() . $path)
            ->setHeaders("WWW-Authenticate: Basic realm=org.collectionspace.services")
            ->setHeaders("Authorization: Basic " . $auth);
        $client = $this->addParams($client, $params);
        return $client;

    }

    function addParams(Zend_Http_Client $client, $params) {
        foreach ($params as $param){
            $client->setParameterGet($param[0], $param[1]);
        }
        return $client;
    }

    function getAuthorization() {
        $username = $this->getUsername();
        $password = $this->getPassword();
        return base64_encode($username . ":" . $password);
    }

    function getUsername() {
        return "admin@core.collectionspace.org";
    }

    function getPassword() {
        return "Administrator";
    }

    function getBaseUrl() {
        return "http://demo.collectionspace.org:8180";
    }

    function getBaseServiceUrl() {
        return $this->getBaseUrl() . "/cspace-services/";
    }



}