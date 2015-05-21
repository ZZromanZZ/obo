<?php

/**
 * This file is part of the Obo framework for application domain logic.
 * Obo framework is based on voluntary contributions from different developers.
 *
 * @link https://github.com/obophp/obo
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

namespace obo;

abstract class Entity  extends \obo\Object {

    /**
     * @var boolean
     */
    private $initialized = false;

    /**
     * @var boolean
     */
    private $basedInRepository = null;

    /**
     * @var string
     */
    private $entityIdentificationKey = null;

    /**
     * @var \obo\EntityProperties
     */
    private $propertiesObject = null;

    /**
     * @var array
     */
    private $propertiesChanges = array();

    /**
     * @var boolean
     */
    private $saveInProgress = false;

    /**
     * @var \obo\Interfaces\IDataStorage
     */
    private $dataStorage = null;

    public function __construct() {

    }

    public function __destruct() {

    }

    /**
     * @param string $propertyName
     * @return mixed
     */
    public function &__get($propertyName) {
        return $this->valueForPropertyWithName($propertyName);
    }

    /**
     * @param string $propertyName
     * @param mixed $value
     * @return mixed
     */
    public function __set($propertyName, $value) {
        return $this->setValueForPropertyWithName($value, $propertyName);
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function __isset($name) {
        return $this->hasPropertyWithName($name);
    }

    /**
     * @throws \obo\Exceptions\Exception
     */
    public function __wakeup() {
        throw new \obo\Exceptions\Exception('Obo entity is not unserializable');
    }

    /**
     * @throws \obo\Exceptions\Exception
     */
    public function __sleep() {
        throw new \obo\Exceptions\Exception('Obo entity is not serializable');
    }

    /**
     * @return \obo\Interfaces\IDataStorage
     */
    public function dataStorage() {
        return $this->dataStorage;
    }

    /**
     * @param \obo\Interfaces\IDataStorage $dataStorage
     * @return \obo\Interfaces\IDataStorage
     * @throws \obo\Exceptions\Exception
     */
    public function setDataStorage(\obo\Interfaces\IDataStorage $dataStorage) {
        if ($this->isInitialized()) throw new \obo\Exceptions\Exception("You can't change datastorage, entity has been initialized");
        return $this->dataStorage = $dataStorage;
    }

    /**
     * @return \obo\EntityProperties
     */
    private function propertiesObject() {
        if (!is_null($this->propertiesObject)) return $this->propertiesObject;
        $propertiesClassName = $this->entityInformation()->propertiesClassName;
        return $this->propertiesObject = new $propertiesClassName($this);;
    }

    /**
     * @return \obo\Carriers\EntityInformationCarrier
     */
    public static function entityInformation() {
        return \obo\Services::serviceWithName(\obo\obo::ENTITIES_INFORMATION)->informationForEntityWithClassName(self::className());
    }

    /**
     * @param string $propertyName
     * @return \obo\Carriers\PropertyInformationCarrier
     */
    public static function informationForPropertyWithName($propertyName) {
        return self::entityInformation()->informationForPropertyWithName($propertyName);
    }

    /**
     * @return \obo\Carriers\PropertyInformationCarrier[]
     */
    public static function propertiesInformation() {
        return self::entityInformation()->propertiesInformation;
    }

    /**
     * @param string $propertyName
     * @return bool
     */
    public static function hasPropertyWithName($propertyName) {
        return self::entityInformation()->existInformationForPropertyWithName($propertyName);
    }

    /**
     * @return array
     */
    public function propertiesChanges() {
        return $this->propertiesChanges;
    }

    /**
     * @return void
     * @throws \obo\Exceptions\Exception
     */
    public function markUnpersitedPropertiesAsPersisted() {
        $backTrace = \debug_backtrace(null, 2);

        if (($backTrace[1]["class"] === "obo\\EntityManager" AND $backTrace[1]["function"] === "saveEntity") OR ($backTrace[1]["class"] === "obo\\Entity" AND $backTrace[1]["function"] === "discardNonPersitedChanges")) {
            foreach($this->propertiesChanges as $propertyName => $changeStatus) {
                $this->propertiesChanges[$propertyName]["persisted"] = true;
                $this->propertiesChanges[$propertyName]["lastPersistedValue"] = $this->propertiesChanges[$propertyName]["newValue"];
            }
        } else {
            throw new \obo\Exceptions\Exception("MarkUnpersitedPropertiesAsPersisted method can be only called from the obo framework");
        }
    }

    /**
     * @return mixed
     */
    public function primaryPropertyValue() {
        return $this->valueForPropertyWithName($this->entityInformation()->primaryPropertyName);
    }

    /**
     * @param string $propertyName
     * @param boolean $entityAsPrimaryPropertyValue
     * @param bool $triggerEvents
     * @throws Exceptions\PropertyNotFoundException
     * @throws Exceptions\ServicesException
     * @return mixed
     */
    public function &valueForPropertyWithName($propertyName, $entityAsPrimaryPropertyValue = false, $triggerEvents = true) {
        if (!$this->hasPropertyWithName($propertyName)) {

            if (($pos = \strpos($propertyName, "_")) AND (($entity = $this->valueForPropertyWithName(\substr($propertyName, 0, $pos))) instanceof \obo\Entity)) {
                return $entity->valueForPropertyWithName(substr($propertyName, $pos + 1), $entityAsPrimaryPropertyValue, $triggerEvents);
            }

            throw new \obo\Exceptions\PropertyNotFoundException("Property with name '{$propertyName}' can't be read, does not exist in entity '" . $this->className() . "'");
        }

        $propertyInformation = $this->informationForPropertyWithName($propertyName);

        if ($triggerEvents) {
            \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("beforeRead" . \ucfirst($propertyName), $this, array("entityAsPrimaryPropertyValue" => $entityAsPrimaryPropertyValue));
        }

        if (\is_null($propertyInformation->getterName)) {
            $value = $this->propertiesObject()->$propertyName;
        } else {
            $value = $this->propertiesObject()->{$propertyInformation->getterName}();
        }

        if ($triggerEvents) {
            \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("afterRead" . \ucfirst($propertyName), $this, array("entityAsPrimaryPropertyValue" => $entityAsPrimaryPropertyValue));
        }

        if ($entityAsPrimaryPropertyValue === true AND $value instanceof \obo\Entity) $value = $value->primaryPropertyValue();

        return $value;
    }

    /**
     * @param mixed $value
     * @param string $propertyName
     * @param bool $triggerEvents
     * @throws \obo\Exceptions\PropertyNotFoundException
     * @return mixed
     */
    public function &setValueForPropertyWithName($value, $propertyName, $triggerEvents = true) {
        if (!$this->hasPropertyWithName($propertyName)) {
            if (($pos = \strpos($propertyName, "_")) AND (($entity = $this->valueForPropertyWithName(\substr($propertyName, 0, $pos))) instanceof \obo\Entity)) {
                return $entity->setValueForPropertyWithName($value, substr($propertyName, $pos + 1));
            }
            throw new \obo\Exceptions\PropertyNotFoundException("Can't write to the property with name '{$propertyName}', does not exist in entity '".$this->className()."'");
        }

        $propertyInformation = $this->informationForPropertyWithName($propertyName);

        if ($dataType = $propertyInformation->dataType) {
            $value = $dataType->sanitizeValue($value);
            if (\is_null($value) AND !$propertyInformation->nullable) throw new \obo\Exceptions\Exception("Property '{$propertyName}' in entity '" . $this->entityInformation()->className . "' cannot be null.  Consider using obo-nullable annotation.");
            $dataType->validate($value);
        }

        if (!\obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->isActiveIgnoreNotificationForEntity($this) AND $triggerEvents) {
            $change = false;
            $oldValue = $this->valueForPropertyWithName($propertyName);
            \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("beforeWrite" . \ucfirst($propertyName), $this, array("propertyValue" => array("old" => $oldValue, "new" => &$value)));

            if (\is_object($value) AND ($value instanceof \obo\Entity OR ($value instanceof \obo\Relationships\EntitiesCollection))) {
                if ($value instanceof \obo\Entity) {
                    if (\is_null($this->valueForPropertyWithName($propertyName, true))) {
                        $change = true;
                    } else {
                        $primaryPropertyValue = $value->valueForPropertyWithName($value->entityInformation()->primaryPropertyName);
                        if ($this->valueForPropertyWithName($propertyName, true) !== $primaryPropertyValue) $change = true;
                    }
                }
            } else {
                if ($this->valueForPropertyWithName($propertyName, true) !== $value) $change = true;
            }

            if ($change) {
                \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("beforeChange" . \ucfirst($propertyName), $this, array("propertyValue" => array("old" => $oldValue, "new" => $value)));
            }
        }

        if (\is_null($propertyInformation->setterName)) {
            $this->propertiesObject()->$propertyName = $value;
        } else {
            $this->propertiesObject()->{$propertyInformation->setterName}($value);
        }

        if (!\obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->isActiveIgnoreNotificationForEntity($this) AND $triggerEvents) {
            \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("afterWrite" . \ucfirst($propertyName), $this, array("propertyValue" => array("old" => $oldValue, "new" => $value)));
            if ($change) {
                \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("afterChange" . \ucfirst($propertyName), $this,  array("propertyValue" => array("old" => $oldValue, "new" => $value)));
                if(isset($this->propertiesChanges[$propertyName])) {
                    $compareValue = $value;

                    if ($compareValue instanceof \obo\Entity) {
                        $compareValue = $compareValue->valueForPropertyWithName($compareValue->entityInformation()->primaryPropertyName);
                    }

                    if ($this->propertiesChanges[$propertyName]["originalValue"] === $this->propertiesChanges[$propertyName]["lastPersistedValue"] AND $this->propertiesChanges[$propertyName]["originalValue"] === $compareValue) {
                        unset($this->propertiesChanges[$propertyName]);
                    } else {
                        $this->propertiesChanges[$propertyName]["newValue"] = $value;
                        $this->propertiesChanges[$propertyName]["persisted"] = false;
                    }

                } else {
                    $this->propertiesChanges[$propertyName] = array("originalValue" => $oldValue, "lastPersistedValue" => $oldValue, "newValue" => $value, "persisted" => false);
                }
            }
        }

        return $value;
    }

    /**
     * @param array | \Iterator | null $onlyFromList
     * @param boolean $entityAsPrimaryPropertyValue
     * @return array
     */
    public function propertiesAsArray($onlyFromList = null, $entityAsPrimaryPropertyValue = true) {
       $data = array();

       if (!\is_null($onlyFromList)) {
           $propertiesNames = \array_keys((array) $onlyFromList);
       } else {
           $propertiesNames = \array_keys((array) $this->propertiesInformation());
       }

       foreach ($propertiesNames as $propertyName) {
            try {
                $data[$propertyName] = $this->valueForPropertyWithName($propertyName, $entityAsPrimaryPropertyValue);
            } catch (\obo\Exceptions\PropertyNotFoundException $exc) {}
       }

        return $data;
    }

    /**
     * @param array | \Iterator $data
     * @return void
     */
    public function changeValuesPropertiesFromArray($data) {
        foreach ($data as $propertyName => $value ) {
            $this->setValueForPropertyWithName($value, $propertyName);
        }
    }

    /**
     * @param array | \Iterator | null $onlyFromList
     * @param boolean $entityAsPrimaryPropertyValue
     * @return array
     */
    public function changedProperties($onlyFromList = null, $entityAsPrimaryPropertyValue = true, $onlyNonPersitentChanges = false) {
        if ($this->isBasedInRepository()) {

            $propertiesChanges = $this->propertiesChanges;

            if ($onlyNonPersitentChanges) {
                foreach($propertiesChanges as $propertyName => $changeStatus) {
                    if ($changeStatus["persisted"]) unset($propertiesChanges[$propertyName]);
                }
            }

            if (\is_null($onlyFromList)) {
                return $this->propertiesAsArray($propertiesChanges, $entityAsPrimaryPropertyValue);
            } else {
                return $this->propertiesAsArray(array_flip(array_intersect(array_keys($onlyFromList), array_keys($propertiesChanges))), $entityAsPrimaryPropertyValue);
            }

        } else {
            return $this->propertiesAsArray($onlyFromList, $entityAsPrimaryPropertyValue);
        }
    }

    /**
     * @return boolean
     */
    public function isInitialized() {
        return $this->initialized;
    }

    /**
     * @return \obo\Entity
     */
    public function setInitialized() {
        \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->turnOffIgnoreNotificationForEntity($this);
        \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("beforeInitialize", $this);
        $this->initialized = true;
        \obo\Services::serviceWithName(\obo\obo::EVENT_MANAGER)->notifyEventForEntity("afterInitialize", $this);
        return $this;
    }

    /**
     * @return boolean
     */
    public function isSaveInProggres(){
        return $this->saveInProgress;
    }

    /**
     * @param boolean $value
     */
    public function setSaveInProgress($value = true){
        $this->saveInProgress = (bool)$value;
    }

    /**
     * @return boolean
     */
    public function isBasedInRepository() {
        if (!is_null($this->basedInRepository)) return $this->basedInRepository;
        $managerName = $this->entityInformation()->managerName;
        return $this->setBasedInRepository($managerName::isEntityBasedInRepository($this));
    }

    /**
     * @param boolean $state
     * @return boolean
     */
    public function setBasedInRepository($state) {
        return $this->basedInRepository = (bool) $state;
    }

    /**
     * @return boolean
     */
    public function isDeleted() {
        return \is_null($propertyNameForSoftDelete = $this->entityInformation()->propertyNameForSoftDelete) ? false : (bool) $this->valueForPropertyWithName($propertyNameForSoftDelete);
    }

    /**
     * @return string
     */
    public function entityIdentificationKey() {
        if (\is_null($this->entityIdentificationKey)) return $this->entityIdentificationKey = \obo\Services::serviceWithName(\obo\obo::IDENTITY_MAPPER)->identificationKeyForEntity($this);
        return $this->entityIdentificationKey;
    }

    /**
     * @return string
     */
    public function objectIdentificationKey() {
        return spl_object_hash($this);
    }

    /**
     * @return void
     * @throws \obo\Exceptions\Exception
     */
    public function discardNonPersitedChanges() {
        if ($this->saveInProgress) throw new \obo\Exceptions\Exception("Can't discard changes, the entity is in the process of saving");
        foreach ($this->propertiesChanges as $propertyName => $changeStatus) $this->setValueForPropertyWithName($changeStatus["lastPersistedValue"], $propertyName);
        $this->markUnpersitedPropertiesAsPersisted();
    }

    /**
     * @return void
     * @throws \obo\Exceptions\Exception
     */
    public function discardChanges() {
        if ($this->saveInProgress) throw new \obo\Exceptions\Exception("Can't discard changes, the entity is in the process of saving");
        foreach ($this->propertiesChanges as $propertyName => $changeStatus) $this->setValueForPropertyWithName($changeStatus["oldValue"], $propertyName);
    }

    /**
     * @return \obo\Entity
     */
    public function save() {
        $managerName = $this->entityInformation()->managerName;
        $managerName::saveEntity($this);
        return $this;
    }

    /**
     * @return void
     */
    public function delete() {
        $managerName = $this->entityInformation()->managerName;
        $managerName::deleteEntity($this);
    }

    /**
     * @return array
     */
    public function dump() {
        $arguments = func_get_args();
        $classInformation = $this->entityInformation();

        if (!isset($arguments[0])) {
            $dump = array(
                "className" => $classInformation->className,
                "managerName" => $classInformation->managerName,
                "repositoryName" => $classInformation->repositoryName,
                "primaryPropertyName" => $classInformation->primaryPropertyName,
                "properties" => array()
            );
        }

        foreach ($this->propertiesInformation() as $propertyInformation) {
            $propertyValue = $this->valueForPropertyWithName($propertyInformation->name);
            if (!is_null($propertyInformation->relationship)) {

                if (isset($propertyInformation->relationship->entityClassNameToBeConnectedInPropertyWithName) AND $propertyInformation->relationship->entityClassNameToBeConnectedInPropertyWithName) {
                    $connectedEntity = $this->valueForPropertyWithName($propertyInformation->relationship->entityClassNameToBeConnectedInPropertyWithName);
                } else {
                    $connectedEntity = $propertyInformation->relationship->entityClassNameToBeConnected;
                }

                $connectedEntityInformation = $connectedEntity::entityInformation();
                $relationshipInformation = array(
                    "relationship" => $propertyInformation->relationship->className(),
                    "className" => $connectedEntityInformation->className,
                    "managerName" => $connectedEntityInformation->managerName,
                    "repositoryName" => $connectedEntityInformation->repositoryName,
                    "primaryPropertyName" => $connectedEntityInformation->primaryPropertyName,
                );

                if (!is_null($propertyValue) AND !($propertyValue instanceof \obo\Relationships\EntitiesCollection AND !count($propertyValue))) {

                    if (isset($arguments[0])

                        AND $arguments[0]->className() == $connectedEntityInformation->className
                        AND $arguments[0]->valueForPropertyWithName($arguments[0]->entityInformation()->primaryPropertyName) == $propertyValue->valueForPropertyWithName($connectedEntityInformation->primaryPropertyName)
                    ) {
                        $relationshipInformation["entity"] = "**RECURSION**";
                    } else {
                        if($propertyInformation->relationship->className() == "obo\\Relationships\\One") {
                            $relationshipInformation["entity"] = $propertyValue->dump($this);
                        } else {
                            $relationshipInformation["entitiesColection"] = $propertyValue->dump($this);
                        }
                    }
                }

                $propertyValue = $relationshipInformation;
            }
            $dump["properties"][$propertyInformation->name] = $propertyValue;

        }

        return $dump;
    }
}
