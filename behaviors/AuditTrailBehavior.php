<?php

namespace dpodium\yii2\audittrail\behaviors;

use Yii;
use yii\db\ActiveRecord;
use yii\base\InvalidConfigException;
use yii\base\InvalidValueException;
use yii\helpers\Json;

/**
 * Behavior which enables a model to be audited. Each modification (insert, update and delete)
 * will be logged with the changed field values. 
 * To enable the behavior on a model simply add it to its behaviors. Further configuration is
 * possible. Check out this classes attributes to see what options there are.
 * 
 * @author Pascal Mueller, AS infotrack AG
 * @link http://www.asinfotrack.ch
 * @license MIT
 * @extendedby Darren Ng, Dynamic Podium (http://www.dpodium.com)
 */
class AuditTrailBehavior extends \yii\base\Behavior {

    //constants for entry-types
    const AUDIT_TYPE_INSERT = 'insert';
    const AUDIT_TYPE_UPDATE = 'update';
    const AUDIT_TYPE_DELETE = 'delete';

    /**
     * @var string[] holds all allowed audit types
     */
    public static $AUDIT_TYPES = [self::AUDIT_TYPE_INSERT, self::AUDIT_TYPE_UPDATE, self::AUDIT_TYPE_DELETE];

    /**
     * @var string[] if defined, the listed attributes will be ignored. Good examples for
     * fields to ignore would be the db-fields of TimestampBehavior or BlameableBehavior.
     */
    public $ignoredAttributes = [];

    /**
     * @var \Closure|null optional closure to return the timestamp of an event. It needs to be
     * in the format 'function() { }' returning an integer. If not set 'time()' is used.
     */
    public $timestampCallback = null;

    /**
     * @var integer|\Closure|null the user id to use if console actions modify a model.
     * If a closure is used, use the 'function() { }' and return an integer or null. 
     */
    public $consoleUserId = 0;

    /**
     * @var boolean if set to true, the data fields will be logged upon insert. Defaults to true.
     */
    public $logValuesOnInsert = true;

    /**
     * @var boolean if set to true, the data fields will be logged upon delete. Defaults to true.
     */
    public $logValuesOnDelete = true;

    /**
     * @var boolean if set to true, the update action will be logged upon update even if no changes. Defaults to false.
     */
    public $logEmptyUpdate = false;

    /**
     * @var boolean if set to true, inserts will be logged (default: true)
     */
    public $logInsert = true;

    /**
     * @var boolean if set to true, updates will be logged (default: true)
     */
    public $logUpdate = true;

    /**
     * @var boolean if set to true, deletes will be logged (default: true)
     */
    public $logDelete = true;

    /**
     * @var \Closure[] contains an array with a model attribute as key and either a string with
     * a default yii-format or a closure as its value. Example:
     * <code>
     * 		[
     * 			'title'=>function($value) {
     * 				return Html::tag('strong', $value);
     * 			},
     * 			'email'=>'email',
     * 		]
     * </code>	 *
     * This provides the AuditTrail-widget the ability to render related objects or complex value instead of
     * raw data changed. You could for example display a users name instead of his plain id.
     *
     * Make sure each closure is in the format 'function ($value)'.
     */
    public $attributeOutput = [];

    /**
     * @var string|\Closure the param key to use to get change remark from request
     * If a closure is used, use the 'function() { }' and return a string or null. 
     */
    public $changeRemark = '__change_remark';

    /**
     * @var string the name of the ActiveRecord model class to use (default: \dpodium\yii2\audittrail\models\AuditTrailEntry)
     */
    public $entryModelClass = null;

    /**
     * @var string the name of the search model class to use (default: \dpodium\yii2\audittrail\models\AuditTrailEntrySearch)
     */
    public $searchModelClass = null;

    /*
     * @var \Closure[] contains an array with a model attribute as key and a closure as its value.
     * 
     * This provides the behavior the ability to translate any values before storing it in database.
     */
    public $convertAttributes = [];

    /*
     * @var string default 2-code/4-code language to use for converting attributes for logging. Defaults to English (en).
     */
    public $defaultLanguage = 'en';

    /**
     * @var \Closure the function to perform custom logging.
     * Use 'function(\dpodium\yii2\audittrail\models\AuditTrailEntry) { }' and return the entry or null (stops the logging)
     */
    public $customLog = null;
    
    /**
     * @var boolean whether to store benchmark numbers.
     */
    public $enableBenchmark = true;
    
    /**
     * @var string comma-separated scenarios to look for to log, if unspecified, defaults to log all
     */
    public $scenarios;
    
    private $milestoneTime = null;

    /**
     * @inheritdoc
     */
    public function attach($owner) {
        //assert owner extends class ActiveRecord
        if (!($owner instanceof ActiveRecord)) {
            throw new InvalidConfigException(Yii::t('app', 'AuditTrailBehavior can only be applied to classes extending \yii\db\ActiveRecord'));
        }

        $this->entryModelClass = $this->entryModelClass ? : \dpodium\yii2\audittrail\models\AuditTrailEntry::className();
        $this->searchModelClass = $this->searchModelClass ? : \dpodium\yii2\audittrail\models\AuditTrailEntrySearch::className();

        parent::attach($owner);
    }

    /**
     * @inheritdoc
     */
    public function events() {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'onAfterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'onAfterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE => 'onAfterDelete',
        ];
    }

    /**
     * Handler for after insert event
     * 
     * @param \yii\db\AfterSaveEvent $event the after save event
     */
    public function onAfterInsert($event) {
        if (!$this->logInsert)
            return;
        if ($this->scenarios && !in_array($this->owner->scenario, explode(',', $this->scenarios))) {
            return;
        }
        $entry = $this->createPreparedAuditTrailEntry(static::AUDIT_TYPE_INSERT);

        //if configured write initial values
        if ($this->logValuesOnInsert) {
            $this->getChangedValues($entry, $event);
        }

        $this->saveEntry($entry);
    }

    /**
     * Handler for after update event
     * 
     * @param \yii\db\AfterSaveEvent $event the after save event
     */
    public function onAfterUpdate($event) {
        if (!$this->logUpdate)
            return;
        if ($this->scenarios && !in_array($this->owner->scenario, explode(',', $this->scenarios))) {
            return;
        }
        $entry = $this->createPreparedAuditTrailEntry(static::AUDIT_TYPE_UPDATE);

        //fetch dirty attributes and add changes
        $this->getChangedValues($entry, $event);
        if (count($entry->getChanges()) == 0 && !$this->logEmptyUpdate) {
            return;
        }

        $this->saveEntry($entry);
    }

    /**
     * Handler for before delete event
     * 
     * @param \yii\base\Event $event the after delete event
     */
    public function onAfterDelete($event) {
        if (!$this->logDelete)
            return;
        if ($this->scenarios && !in_array($this->owner->scenario, explode(',', $this->scenarios))) {
            return;
        }
        $entry = $this->createPreparedAuditTrailEntry(static::AUDIT_TYPE_DELETE);

        //if configured write end values
        if ($this->logValuesOnDelete) {
            $this->getChangedValues($entry, null);
        }

        $this->saveEntry($entry);
    }

    /**
     * Creates and returns a preconfigured audit trail model
     * 
     * @param string $changeKind the kind of audit trail entry (use this classes statics)
     * @return \dpodium\yii2\audittrail\models\AuditTrailEntry the entry
     */
    protected function createPreparedAuditTrailEntry($changeKind) {
        $this->milestoneTime = microtime(true);
        $entry = new $this->entryModelClass([
            'happened_at' => $this->getHappenedAt(),
            'type' => $changeKind,
            'change_remark' => $this->getChangeRemark(),
            'url' => isset(Yii::$app->request->absoluteUrl) ? substr(Yii::$app->request->absoluteUrl, 0, 255) : null,
        ]);
        $entry->setOwner($this->owner);
        $this->getUserDetail($entry);
        return $entry;
    }

    /**
     * Returns the user id to use for am audit trail entry
     * 
     * @param \dpodium\yii2\audittrail\models\AuditTrailEntry $entry
     * @return \dpodium\yii2\audittrail\models\AuditTrailEntry the entry
     */
    protected function getUserDetail($entry) {
        if (Yii::$app instanceof \yii\console\Application) {
            if ($this->consoleUserId instanceof \Closure) {
                $entry->user_id = call_user_func($this->consoleUserId);
            } else {
                $entry->user_id = $this->consoleUserId;
            }
        } else if (Yii::$app->user->isGuest) {
            $entry->user_id = null;
        } else {
            $entry->user_id = Yii::$app->user->id;
            $entry->user_ipaddress = isset(Yii::$app->request->userIP) ? Yii::$app->request->userIP : null;
        }
        return $entry;
    }

    /**
     * Returns the change remark provided by user for this update
     * 
     * @return string|null returns either the remark or null
     */
    protected function getChangeRemark() {
        if ($this->changeRemark instanceof \Closure) {
            return call_user_func($this->changeRemark);
        } else {
            return Yii::$app->request->post($this->changeRemark, Yii::$app->request->get($this->changeRemark, null));
        }
    }

    /**
     * Returns the timestamp for the audit trail entry.
     * 
     * @return integer unix-timestamp
     */
    protected function getHappenedAt() {
        if ($this->timestampCallback !== null) {
            return call_user_func($this->timestampCallback);
        } else {
            return time();
        }
    }

    /**
     * Gathers the updated values
     * 
     * @param \dpodium\yii2\audittrail\models\AuditTrailEntry $entry
     * @return \dpodium\yii2\audittrail\models\AuditTrailEntry the entry
     */
    protected function getChangedValues($entry, $event) {
        foreach ($this->getRelevantDbAttributes() as $attrName) {
            $oldVal = null;
            $newVal = $this->owner->{$attrName} !== '' ? $this->owner->{$attrName} : null;
            if ($entry->type == static::AUDIT_TYPE_UPDATE) {
                if (!isset($event->changedAttributes[$attrName])) {
                    continue;
                }
                $oldVal = $event->changedAttributes[$attrName] !== '' ? $event->changedAttributes[$attrName] : null;
            }
            if ($oldVal != $newVal) {
                $entry->setChange($attrName, $oldVal, $newVal);
            }
        }
        if ($this->enableBenchmark) {
            $entry->picoseconds_collect_data = round((microtime(true) - $this->milestoneTime) * pow(10, 6));
            $this->milestoneTime = microtime(true);
        }
        return $entry;
    }

    /**
     * This method is responsible to create a list of relevant db-columns to track. The ones
     * listed to exclude will be removed here already.
     * 
     * @return string[] array containing relevant db-columns
     */
    protected function getRelevantDbAttributes() {
        //get cols from db-schema
        $cols = array_keys($this->owner->getTableSchema()->columns);

        //return if no ignored cols
        if (count($this->ignoredAttributes) === 0) {
            return $cols;
        }

        //remove ignored cols and return
        $colsFinal = [];
        foreach ($cols as $c) {
            if (in_array($c, $this->ignoredAttributes)) {
                continue;
            }
            $colsFinal[] = $c;
        }

        return $colsFinal;
    }

    /**
     * This method converts the changed attributes to human readable values for saving
     * @param \dpodium\yii2\audittrail\models\AuditTrailEntry $entry
     * 
     * @return \dpodium\yii2\audittrail\models\AuditTrailEntry the entry
     */
    protected function convertAttributeForSaving($entry) {
        if (empty($this->convertAttributes)) {
            return;
        }
        $user_language = Yii::$app->language;
        Yii::$app->language = $this->defaultLanguage;
        $changes = $entry->changes ? : [];
        foreach ($changes as $change) {
            if (isset($this->convertAttributes[$change->attr])) {
                if (isset($change->from)) {
                    $change->from = $change->from . ' :: ' . call_user_func($this->convertAttributes[$change->attr], $change->from);
                }
                if (isset($change->to)) {
                    $change->to = $change->to . ' :: ' . call_user_func($this->convertAttributes[$change->attr], $change->to);
                }
            }
        }
        Yii::$app->language = $user_language;
    }

    /**
     * Saves the entry and outputs an exception describing the problem if necessary
     * 
     * @param \dpodium\yii2\audittrail\models\AuditTrailEntry $entry
     * @throws InvalidValueException if entry couldn't be saved (validation error)
     */
    protected function saveEntry($entry) {
        $this->convertAttributeForSaving($entry);

        if (isset($this->customLog) && $this->customLog instanceof \Closure) {
            $entry = call_user_func($this->customLog, $entry);
            // if custom log returns null, means abort logging
            if (!isset($entry)) {
                return;
            }
        }
        if ($this->enableBenchmark) {
            $entry->picoseconds_convert_attribute = round((microtime(true) - $this->milestoneTime) * pow(10, 6));
        }
        //do nothing if successful
        if ($entry->save()) {
            return;
        }

        //otherwise throw exception
        $lines = [];
        foreach ($entry->getErrors() as $attr => $errors) {
            foreach ($errors as $err) {
                $lines[] = $err;
            }
        }
        throw new InvalidValueException(Yii::t('app', 'Error while saving audit-trail-entry: {0}', [ implode(', ', $lines)]));
    }

}
