<?php

namespace Jarves\Admin;

use Jarves\Configuration\Condition;
use Jarves\Configuration\Field;
use Jarves\Configuration\Model;
use Jarves\Jarves;
use Jarves\Exceptions\ObjectNotFoundException;
use Jarves\Exceptions\Rest\ValidationFailedException;
use Jarves\Objects;
use Jarves\Tools;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;

class ObjectCrud extends ContainerAware implements ObjectCrudInterface
{
    /**
     * Defines the table which should be accessed.
     * This variable has to be set by any subclass.
     *
     * Use this only if you know, what you're doing,
     * normally this comes from the object settings.
     *
     * @var string table name
     */
    protected $table = '';

    /**
     * Defines the object which should be listed.
     *
     * @var string object key
     */
    protected $object = '';

    /**
     * Copy of the object definition
     *
     * @var \Jarves\Configuration\Object
     */
    protected $objectDefinition;

    /**
     * @var Jarves
     */
    protected $jarves;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Defines your primary fields as a array.
     * Example: $primary = array('id');
     * Example: $primary = array('id', 'name');
     *
     * Use this only if you know, what you're doing,
     * normally this comes from the object settings.
     *
     * @abstract
     * @var array
     */
    protected $primary = array();

    /**
     * The primary key of the current object.
     * If the class created a item through addItem(),
     * it contains the primary key of the newly created
     * item.
     *
     * array(
     *    'id' => 1234
     * )
     *
     * array(
     *     'id' => 1234,
     *     'subId' => 5678
     * )
     *
     * @var array
     * @see getPrimaryKey()
     */
    protected $primaryKey = array();

    /**
     * Defines the fields of your edit/add window which should be displayed.
     * Can contains several fields nested, via 'children', also type 'tab' are allowed.
     * Every jarves.field is allowed.
     *
     * @abstract
     * @var array
     */
    protected $fields = array();

    /**
     * Defines additional to $fields more selected field keys, so you can
     * provide through the API more values without defining those in the editor ($fields) itself.
     *
     * Comma separated or as array.
     *
     * @var string|array
     */
    protected $extraSelection = array();

    /**
     * Defines the fields of your table which should be displayed.
     * Only one level, no children, no tabs. Use the window editor,
     * to get the list of possible types.
     *
     * @abstract
     * @var array
     */
    protected $columns = null;

    /**
     * Defines how many rows should be displayed per page.
     *
     * @var integer number of rows per page
     */
    protected $defaultLimit = 15;

    /**
     * Order field
     *
     * @var string
     */
    protected $orderBy = '';

    /**
     * Order field
     *
     * @private
     * @var string
     */
    protected $customOrderBy = '';

    /**
     * Order direction
     *
     * @var string
     */
    protected $orderByDirection = 'ASC';

    /**
     * Default order
     *
     * array(
     *      array('field' => 'group_id', 'direction' => 'asc'),
     *      array('field' => 'title', 'direction' => 'asc')
     * );
     *
     * or
     *
     * array(
     *     'group_id' => 'asc',
     *     'title' => 'desc'
     * )
     *
     * @var array
     */
    protected $order = array();

    /**
     * Contains the fields for the search.
     *
     * @var array
     */
    protected $filter = array();

    /**
     * Defines the icon for the add button. Relative to media/ or #className for vector images
     *
     * @var string name of image
     */
    protected $addIcon = '#icon-plus-5';

    /**
     * Defines the icon for the edit button. Relative to media/ or #className for vector images
     *
     * @var string name of image
     */
    protected $editIcon = '#icon-pencil-2';

    /**
     * Defines the icon for the remove/delete button. Relative to media/ or #className for vector images
     *
     * @var string name of image
     */
    protected $removeIcon = '#icon-minus-5';

    /**
     * Defines the icon for the remove/delete button. Relative to media/ or #className for vector images
     *
     * @var string name of image
     */
    protected $removeIconItem = '#icon-minus-5';

    /**
     * The system opens this entrypoint when clicking on the add newt_button(left, top, text)n.
     * Default is <current>/.
     *
     * Relative or absolute paths are allowed.
     * Empty means current entrypoint.
     *
     * @var string
     */
    protected $addEntrypoint = '';

    /**
     * The system opens this entrypoint when clicking on the edit button.
     * Default is <current>/.
     *
     * Relative or absolute paths are allowed.
     * Empty means current entrypoint.
     *
     * @var string
     */
    protected $editEntrypoint = '';

    /**
     * @var string
     */
    protected $removeEntrypoint = '';

    /**
     * @var bool
     */
    protected $withNewsFeed = true;

    /**
     * Allow a client to select own fields through the REST api.
     * (?fields=...)
     *
     * @var bool
     */
    protected $allowCustomSelectFields = true;

    protected $nestedRootEdit = false;
    protected $nestedRootAdd = false;
    protected $nestedAddWithPositionSelection = true;
    protected $nestedRootAddIcon = '#icon-plus-2';
    protected $nestedRootAddLabel = '[[New Root]]';
    protected $nestedRootRemove = false;

    protected $nestedRootEditEntrypoint = 'root/';
    protected $nestedRootAddEntrypoint = 'root/';

    protected $nestedRootRemoveEntrypoint = 'root/';

    /**
     * Defines whether the add button should be displayed
     *
     * @var boolean
     */
    protected $add = false;
    protected $newLabel = '[[New]]';
    protected $addMultiple = false;
    protected $addMultipleFieldContainerWidth = '70%';

    protected $addMultipleFields = array();

    protected $addMultipleFixedFields = array();

    protected $startCombine = false;

    /**
     * Defines whether the remove/delete button should be displayed
     * Also on each row the Delete-Button and the checkboxes.
     *
     * @var boolean
     */
    protected $remove = false;
    /**
     * Defines whether the edit button should be displayed
     *
     * @var boolean
     */
    protected $edit = false;

    protected $nestedMoveable = true;

    /**
     * Defines whether the list windows should display the language select box.
     * Note: Your table need a field 'lang' varchar(2). The windowList class filter by this.
     *
     * @var bool
     */
    protected $multiLanguage = false;

    /**
     * Defines whether the list windows should display the domain select box.
     * Note: Your table need a field 'domain_id' int. The windowList class filter by this.
     *
     * @var bool
     */
    protected $domainDepended = false;

    /**
     * Defines whether the workspace slider should appears or not.
     * Needs a column workspace_id in the table or active workspace at object.
     *
     * @var bool
     */
    protected $workspace = false;

    /**
     * @var string
     */
    protected $itemLayout = '';

    /**
     * @var array
     */
    protected $filterFields = array();

    /**
     * Flatten list of fields.
     *
     * @var Field[]
     */
    protected $_fields = array();

    /**
     * Defines whether the class checks, if the user has account to requested object item.
     *
     * @var boolean
     */
    protected $permissionCheck = true;

    /**
     * If the object is a nested set, then you should switch this property to true.
     *
     * @var bool
     */
    protected $asNested = false;


    /**
     * @var int
     */
    protected $itemsPerPage = 15;

    /**
     * Uses the HTTP 'PATCH' instead of the 'PUT'.
     * 'PUT' requires that you send all field, and 'PATCH'
     * only the fields that need to be updated.
     *
     * @var bool
     */
    protected $usePatch = true;

    /**
     * Translate the label/title item of $fields.
     *
     * @param $fields
     */
    public function translateFields(&$fields)
    {
        if (is_array($fields)) {
            foreach ($fields as &$field) {
                $this->translateFields($field);
            }
        } elseif (is_string($fields) && substr($fields, 0, 2) == '[[' && substr($fields, -2) == ']]') {
            $fields = $this->getJarves()->getTranslator()->t(substr($fields, 2, -2));
        }

    }

    /**
     * @param bool $withoutObjectCheck
     *
     * @throws ObjectNotFoundException
     */
    public function initialize($withoutObjectCheck = false)
    {
        if ($this->objectDefinition) {
            return;
        }

        $this->objectDefinition = $this->getJarves()->getObjects()->getDefinition($this->getObject());

        if (!$this->objectDefinition && $this->getObject() && !$withoutObjectCheck) {
            throw new ObjectNotFoundException("Can not find object '" . $this->getObject() . "'");
        }


        if ($this->objectDefinition) {
            if (!$this->table) {
                $this->table = $this->objectDefinition->getTable();
            }
            if (!$this->fields) {
                $this->fields = $this->objectDefinition->getFields();
            }
            if (!isset($this->titleField)) {
                $this->titleField = $this->objectDefinition->getLabel();
            }
        }

        //resolve shortcuts
        if ($this->fields) {
            $this->prepareFieldDefinition($this->fields);
            $this->translateFields($this->fields);
        }

        if ($this->fields) {
            foreach ($this->fields as $key => &$field) {
                if (is_array($field)) {
                    $fieldInstance = new Field(null, $this->getJarves());
                    $fieldInstance->fromArray($field);
                    $fieldInstance->setId($key);
                    $field = $fieldInstance;
                }
            }
        }

        if ($this->addMultipleFixedFields) {
            foreach ($this->addMultipleFixedFields as $key => &$field) {
                if (is_array($field)) {
                    $fieldInstance = new Field(null, $this->getJarves());
                    $fieldInstance->fromArray($field);
                    $fieldInstance->setId($key);
                    $field = $fieldInstance;
                }
            }
        }

        if ($this->addMultipleFields) {
            foreach ($this->addMultipleFields as $key => &$field) {
                if (is_array($field)) {
                    $fieldInstance = new Field(null, $this->getJarves());
                    $fieldInstance->fromArray($field);
                    $fieldInstance->setId($key);
                    $field = $fieldInstance;
                }
            }
        }

        if ($this->columns) {
            $this->prepareFieldDefinition($this->columns);
            $this->translateFields($this->columns);
        }

        if ($this->addMultipleFields) {
            $this->prepareFieldDefinition($this->addMultipleFields);
            $this->translateFields($this->addMultipleFields);
        }

        if ($this->addMultipleFixedFields) {
            $this->prepareFieldDefinition($this->addMultipleFixedFields);
            $this->translateFields($this->addMultipleFixedFields);
        }

        //do magic with type select and add all fields to _fields.
        if (count($this->fields) > 0) {
            $this->prepareFieldItem($this->fields);
        }

        if (is_string($this->primary)) {
            $this->primary = explode(',', str_replace(' ', '', $this->primary));
        }

        if (!$this->order || count($this->order) == 0) {
            /* compatibility */
            $this->orderByDirection = (strtolower($this->orderByDirection) == 'asc') ? 'asc' : 'desc';
            if ($this->orderBy) {
                $this->order = array($this->orderBy => $this->orderByDirection);
            }
        }

        if ((!$this->order || count($this->order) == 0) && $this->columns) {
            reset($this->columns);
            $this->order[key($this->columns)] = 'asc';
        }

        //normalize order array
        if (count($this->order) > 0 && is_numeric(key($this->order))) {
            $newOrder = array();
            foreach ($this->order as $order) {
                $newOrder[$order['field']] = $order['direction'];
            }
            $this->order = $newOrder;
        }

        $this->filterFields = array();

        if ($this->filter) {
            foreach ($this->filter as $key => $val) {

                if (is_numeric($key)) {
                    //no special definition
                    $fieldKey = $val;
                    $field = $this->fields[$val];
                } else {
                    $field = $val;
                    $fieldKey = $key;
                }

                //$this->prepareFieldItem($field);
                $this->filterFields[$fieldKey] = $field;
            }
        }

        if (!$this->primary) {
            $this->primary = array();
            if ($this->objectDefinition) {
                foreach ($this->objectDefinition->getPrimaryKeys() as $sfield) {
                    $this->primary[] = $sfield->getId();
                }
            }
        }

        $this->translate($this->nestedRootAddLabel);
        $this->translate($this->newLabel);
    }

    /**
     * @param boolean $withNewsFeed
     */
    public function setWithNewsFeed($withNewsFeed)
    {
        $this->withNewsFeed = $withNewsFeed;
    }

    /**
     * @return boolean
     */
    public function getWithNewsFeed()
    {
        return $this->withNewsFeed;
    }

    /**
     * @param Jarves $jarves
     */
    public function setJarves($jarves)
    {
        $this->jarves = $jarves;
    }

    /**
     * @return Jarves
     */
    public function getJarves()
    {
        return $this->container->get('jarves');
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param $field
     */
    public function translate(&$field)
    {
        if (is_string($field) && substr($field, 0, 2) == '[[' && substr($field, -2) == ']]') {
            $field = $this->getJarves()->getTranslator()->t(substr($field, 2, -2));
        }
    }

    public function getInfo()
    {
        $vars = get_object_vars($this);
        $blacklist = array('objectDefinition', 'entryPoint');
        $result = array();

        foreach ($vars as $var => $val) {
            if (in_array($var, $blacklist)) {
                continue;
            }
            $method = 'get' . ucfirst($var);
            if (method_exists($this, $method)) {
                $result[$var] = $this->$method();
            }
        }

        if ($result['fields']) {
            foreach ($result['fields'] as &$field) {
                if ($field instanceof Model) {
                    $field = $field->toArray();
                }
            }
        }
        if ($result['addMultipleFixedFields']) {
            foreach ($result['addMultipleFixedFields'] as &$field) {
                if ($field instanceof Model) {
                    $field = $field->toArray();
                }
            }
        }
        if ($result['addMultipleFields']) {
            foreach ($result['addMultipleFields'] as &$field) {
                if ($field instanceof Model) {
                    $field = $field->toArray();
                }
            }
        }

        return $result;

    }

    /**
     * prepares $fields. Replace array items which are only a key (with no array definition) with
     * the array definition of the proper field from the object fields.
     *
     * @param $fields
     */
    public function prepareFieldDefinition(&$fields)
    {
        $i = 0;

        foreach ($fields as $key => $field) {
            if (is_numeric($key) && !$field instanceof Field) {

                $newItem = $this->objectDefinition->getField($field);
                if ($newItem) {
                    $newItem = $newItem->toArray();
                } else {
                    continue;
                }
                if (!$newItem['label']) {
                    $newItem['label'] = $field;
                }

                $fields = array_merge(
                    array_slice($fields, 0, $i),
                    array($field => $newItem),
                    array_slice($fields, $i + 1)
                );
                reset($fields);
                $i = -1;
            }
            $i++;
        }

        foreach ($fields as $key => &$field) {
            if (!is_array($field)) {
                continue;
            }

            $oField = $this->objectDefinition->getField($key);
            if ($oField) {
                if (!isset($field['type'])) {
                    $field['type'] = 'predefined';
                }
                if (strtolower($field['type']) == 'predefined' && !isset($field['object'])) {
                    $field['object'] = $this->getObject();
                }
                if (strtolower($field['type']) == 'predefined' && !isset($field['field'])) {
                    $field['field'] = $key;
                }

                if (!isset($field['label'])) {
                    $field['label'] = $oField->getLabel();
                }

                if (!isset($field['desc'])) {
                    $field['desc'] = $oField->getDesc();
                }

                if (!isset($field['label'])) {
                    $field['label'] = $key;
                }
            }

            if (isset($field['depends'])) {
                $this->prepareFieldDefinition($field['depends']);
            }
            if (isset($field['children'])) {
                $this->prepareFieldDefinition($field['children']);
            }
        }
    }

    /**
     * Prepare fields. Loading tableItems by select and file fields.
     *
     * @param array|Field $fields
     *
     * @throws \Exception
     */
    public function prepareFieldItem($fields)
    {
        if (is_array($fields)) {
            foreach ($fields as &$field) {
                $this->prepareFieldItem($field);
            }
        } else {

            /*TODO
            if ($fields['needAccess'] && !Jarves::checkUrlAccess($fields['needAccess'])) {
                $fields = null;

                return;
            }*/

            if (substr($fields->getId(), 0, 2) != '__' && substr($fields->getId(), -2) != '__') {
                switch ($fields->getType()) {
                    case 'predefined':

                        if (!$fields->getObject()) {//&& !$this->getObject()) {
                            throw new \Exception(sprintf(
                                'Fields of type `predefined` need a `object` option. [%s]',
                                json_encode($fields->toArray(), JSON_PRETTY_PRINT)
                            ));
//                        } else if (!$fields->getObject()) {
//                            $fields->setObject($this->getObject());
                        }

                        if (!$fields->getField()) {//&& !$fields->getId()) {
                            throw new \Exception(sprintf(
                                'Fields of type `predefined` need a `field` option. [%s]',
                                json_encode($fields->toArray(), JSON_PRETTY_PRINT)
                            ));
//                        } else if (!$fields->getField()) {
//                            $fields->setField($fields->getId());
                        }

                        $object = $this->getJarves()->getObjects()->getDefinition($fields->getObject());
                        if (!$object) {
                            throw new \Exception(sprintf(
                                'Object `%s` does not exist [%s]',
                                $fields->getObject(),
                                json_encode($fields->toArray(), JSON_PRETTY_PRINT)
                            ));
                        }
                        $def = $object->getField($fields->getField());
                        if (!$def) {
                            $objectArray = $object->toArray();
                            $fields2 = $objectArray['fields'];
                            throw new \Exception(sprintf(
                                "Object `%s` does not have field `%s`. \n[%s]\n[%s]",
                                $fields->getObject(),
                                $fields->getField(),
                                json_encode($fields->toArray(), JSON_PRETTY_PRINT),
                                json_encode($fields2, JSON_PRETTY_PRINT)
                            ));
                        }
                        if ($def) {
                            $fields = $def;
                        }

                        break;
                    case 'select':

//                        if ($fields->getTable()) {
//                            $fields['tableItems'] = dbTableFetchAll($fields['table']);
//                        } else if ($fields['sql']) {
//                            $fields['tableItems'] = dbExFetchAll(str_replace('%pfx%', pfx, $fields['sql']));
//                        } else if ($fields['method']) {
//                            $nam = $fields['method'];
//                            if (method_exists($this, $nam)) {
//                                $fields['tableItems'] = $this->$nam($fields);
//                            }
//                        }
//
//                        if ($fields['modifier'] && !empty($fields['modifier']) &&
//                            method_exists($this, $fields['modifier'])
//                        ) {
//                            $fields['tableItems'] = $this->$fields['modifier']($fields['tableItems']);
//                        }

                        break;
                }
                $this->_fields[$fields->getId()] = $fields;
            }

            if (is_array($fields->getChildren())) {
                $this->prepareFieldItem($fields->getChildren());
            }

        }
    }

    public function getDefaultFieldList()
    {
        $fields = array();
        $objectFields = array_flip(array_keys($this->getObjectDefinition()->getFieldsArray()));

        if ($this->_fields) {
            foreach ($this->_fields as $key => $field) {
                if (isset($objectFields[$key]) && !$field->getCustomSave() && !$field->getStartEmpty()) {
                    $fields[] = $key;
                }
            }
        } else {
            $fields = explode(',', trim(str_replace(' ', '', $this->getObjectDefinition()->getDefaultSelection())));
        }

        if ($this->getMultiLanguage()) {
            $fields[] = 'lang';
        }

        return $fields;
    }

    public function getPosition($pk)
    {
        /*$obj = $this->getJarves()->getObjects()->getClass($this->getObject());
        $primaryKey = $obj->normalizePrimaryKey($pk);

        $condition = $this->getCondition();

        if ($customCondition = $this->getCustomListingCondition())
            $condition = $condition ? array_merge($condition, $customCondition) : $customCondition;

        $options['permissionCheck'] = $this->permissionCheck;
        */
        $obj = $this->getJarves()->getObjects()->getStorageController($this->getObject());
        $primaryKey = $obj->normalizePrimaryKey($pk);
        $items = $this->getItems();

        $position = 0;

        $singlePrimaryKey = null;
        if (count($primaryKey) == 1) {
            $singlePrimaryKey = key($primaryKey);
            $singlePrimaryValue = current($primaryKey);
        }

        foreach ($items as $item) {

            if ($singlePrimaryKey) {
                if ($item[$singlePrimaryKey] == $singlePrimaryValue) {
                    break;
                }
            } else {
                $isItem = true;
                foreach ($primaryKey as $prim => $val) {
                    if ($item[$prim] != $val) {
                        $isItem = false;
                    }
                }
                if ($isItem) {
                    break;
                }
            }

            $position++;
        }

        return $position;

    }

    /**
     *
     * $pk is an array with the primary key values.
     *
     * If one primary key:
     *   array(
     *    'id' => 1234
     *   )
     *
     * If multiple primary keys:
     *   array(
     *    'id' => 1234
     *    'secondId' => 5678
     *   )
     *
     * @param  array $pk
     * @param  array $fields
     * @param  bool $withAcl
     *
     * @return array
     */
    public function getItem($pk, $fields = null, $withAcl = false)
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());
        $pk = $storageController->normalizePrimaryKey($pk);

        $this->primaryKey = $pk;

        if ($this->getPermissionCheck() && !$this->getJarves()->getACL()->checkViewExact($this->getObject(), $pk)) {
            return null;
        }

        $options['fields'] = $this->getSelection($fields, false);
        $options['permissionCheck'] = $this->getPermissionCheck();

        $item = $storageController->getItem($pk, $options);

        //check against additionally our own custom condition
        if ($item && ($condition = $this->getCondition()) && $condition->hasRules()) {
            if (!$condition->satisfy($item, $this->getObject())) {
                $item = null;
            }
        }

        if ($limitDataSets = $this->getObjectDefinition()->getLimitDataSets()) {
            if (!$limitDataSets->satisfy($item, $this->getObject())) {
                return null;
            }
        }

        if ($item && $withAcl) {
            $this->prepareRow($item);
            $this->prepareFieldAcl($item);
        }

        return $item;
    }

    public function prepareFieldAcl(&$item)
    {
        if (false === $item['_editable']) {
            return;
        }
        $def = $this->getObjectDefinition();
        $acl = [];
        foreach ($def->getFields() as $field) {
            if (!$this->getJarves()->getACL()->checkUpdateExact($this->getObject(), $item, [$field->getId()])) {
                $acl[] = $field->getId();
            }
        }

        $item['_notEditable'] = $acl;
    }

    /**
     *
     *   array(
     *       'items' => $items,
     *       'count' => $maxItems,
     *       'pages' => $maxPages
     *   );
     *
     * @param Request $request
     * @param array $filter
     * @param integer $limit
     * @param integer $offset
     * @param string $query
     * @param string $fields
     * @param array $orderBy
     *
     * @return array
     */
    public function getItems($filter = null, $limit = null, $offset = null, $query = '', $fields = null, $orderBy = [], $withAcl = false)
    {
        $options = array();

        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());

        $options['offset'] = $offset;
        $options['limit'] = $limit ? $limit : $this->defaultLimit;

        $condition = $this->getCondition();

        if ($extraCondition = $this->getCustomListingCondition()) {
            $condition->mergeAnd($extraCondition);
        }

        $options['order'] = $orderBy ? : $this->getOrder();
        $options['fields'] = $this->getSelection($fields);

        if ($filter && $filterCondition = self::buildFilter($filter)) {
            $condition->mergeAnd($filterCondition);
        }

        if ($limit = $this->getObjectDefinition()->getLimitDataSets()) {
            $condition->mergeAnd($limit);
        }

        if ($this->getMultiLanguage()) {
            //does the object have a lang field?
            if ($this->objectDefinition->getField('lang')) {
                //add language condition
                $langCondition = new Condition(null, $this->getJarves());
                $langCondition->addAnd(
                    array('lang', '=', substr((string)$this->getRequest()->request->get('lang'), 0, 3))
                );
                $langCondition->addOr(array('lang', 'IS NULL'));

                $condition->addAnd($langCondition);
            }
        }

        if ($query) {
            if ($queryCondition = $this->getQueryCondition($query, $options['fields'])) {
                $condition->mergeAnd($queryCondition);
            }
        }

        if ($this->getPermissionCheck() && $aclCondition = $this->getJarves()->getACL()->getListingCondition($this->getObject())) {
            $condition->mergeAndBegin($aclCondition);
        }

        $items = $storageController->getItems($condition, $options);

        if ($withAcl && is_array($items)) {
            foreach ($items as &$item) {
                if ($item) {
                    $this->prepareRow($item);
                }
            }
        }

        return $items ? : null;
    }

    /**
     * Returns the selection (field names)
     *
     * @param array $fields
     * @param bool $getColumns
     * @return array
     */
    protected function getSelection($fields = '', $getColumns = true)
    {
        $result = [];
        if ($fields && $this->getAllowCustomSelectFields()) {
            if (!is_array($fields)) {
                if ('*' !== $fields) {
                    $result = explode(',', trim(preg_replace('/[^a-zA-Z0-9_\.\-,\*]/', '', $fields)));
                }
            }
        }

        if (!$result && $getColumns) {
            $result = array_keys($this->getColumns() ? : array());
        }

        if (!$result) {
            $result = $this->getDefaultFieldList();
        }

        if ($extraSelects = $this->getExtraSelection()) {
            $result = $result ? array_merge($result, $extraSelects) : $extraSelects;
        }

        return $result;
    }

    protected function getNestedSelection($fields)
    {
        if (is_string($fields)) {
            $fields = explode(',', trim(preg_replace('/[^a-zA-Z0-9_\.\-,\*]/', '', $fields)));
        }

        if ($extraSelects = $this->getExtraSelection()) {
            $fields = $fields ? array_merge($fields, $extraSelects) : $extraSelects;
        }

        return $fields;
    }

    public function getExtraSelection()
    {
        if (is_string($this->extraSelection)) {
            return explode(',', trim(preg_replace('/[^a-zA-Z0-9_\.\-,\*]/', '', $this->extraSelection)));
        }

        return $this->extraSelection;
    }

    public function setExtraSelection($selection)
    {
        $this->extraSelection = $selection;
    }

    protected function getQueryCondition($query, $fields)
    {
        $fields = $this->getSelection($fields);

        $query = preg_replace('/(?<!\\\\)\\*/', '$1%', $query);
        $query = str_replace('\\*', '*', $query);

        $result = [];
        foreach ($fields as $field) {
            if (!$this->getObjectDefinition()->getField($field)) {
                continue;
            }

            if ($result) {
                $result[] = 'OR';
            }

            $result[] = [
                $field,
                'LIKE',
                $query . '%'
            ];
        }

        return $result;
    }

    /**
     * @param  array $filter
     *
     * @return array|null
     */
    public static function buildFilter($filter)
    {
        $condition = null;

        if (is_array($filter)) {
            //build condition query
            $condition = array();
            foreach ($filter as $k => $v) {
                if ($condition) {
                    $condition[] = 'and';
                }

                $k = Tools::camelcase2Underscore($k);

                if (strpos($v, '*') !== false) {
                    $condition[] = array($k, 'LIKE', str_replace('*', '%', $v));
                } else {
                    $condition[] = array($k, '=', $v);
                }
            }
        }

        return $condition;
    }

    /**
     * @param  string $fields
     *
     * @return array
     */
    public function getTreeFields($fields = null)
    {
        //use default fields from object definition
        $definition = $this->objectDefinition;
        $fields2 = array();

        if ($fields && $this->getAllowCustomSelectFields()) {
            if (is_array($fields)) {
                $fields2 = $fields;
            } else {
                $fields2 = explode(',', trim(preg_replace('/[^a-zA-Z0-9_,]/', '', $fields)));
            }
        }

        if ($definition && !$fields2) {

            if ($treeFields = $definition->getTreeFields()) {
                $fields2 = explode(',', trim(preg_replace('/[^a-zA-Z0-9_,]/', '', $treeFields)));
            } else {
                $fields2 = ($definition->getDefaultSelection()) ? explode(
                    ',',
                    trim(preg_replace('/[^a-zA-Z0-9_,]/', '', $definition->getDefaultSelection()))
                ) : array();
            }

            $fields2[] = $definition->getFieldLabel();

            if ($definition->getTreeIcon()) {
                $fields2[] = $definition->getTreeIcon();
            }
        }

        return $fields2;

    }

    /**
     * Returns items per branch.
     *
     * @param  mixed $pk
     * @param  array $filter
     * @param  mixed $fields
     * @param  mixed $scope
     * @param  int $depth
     * @param  int $limit
     * @param  int $offset
     * @param  bool $withAcl
     *
     * @return mixed
     */
    public function getBranchItems(
        $pk = null,
        $filter = null,
        $fields = null,
        $scope = null,
        $depth = 1,
        $limit = null,
        $offset = null,
        $withAcl = false
    ) {

        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());

        if (null !== $pk) {
            $pk = $storageController->normalizePrimaryKey($pk);
        }

        if (null === $pk && $this->getObjectDefinition()->getNestedRootAsObject() && $scope === null) {
            throw new \Exception('No scope defined.');
        }

        $options = array();
        $options['offset'] = $offset;
        $options['limit'] = $limit ? $limit : $this->defaultLimit;

        if (!$fields) {
            $fields = array();
            $fields[] = $this->getObjectDefinition()->getLabelField();

            if ($rootField = $this->getObjectDefinition()->getNestedRootObjectLabelField()) {
                $fields[] = $rootField;
            }

            if ($extraFields = $this->getObjectDefinition()->getNestedRootObjectExtraFields()) {
                $extraFields = explode(',', trim(str_replace(' ', '', $extraFields)));
                foreach ($extraFields as $field) {
                    $fields[] = $field;
                }
            }
            $options['fields'] = implode(',', $fields);
        }

        if ($filter) {
            $conditionObject = $filter instanceof Condition ? $filter : self::buildFilter($filter);
        } else {
            $conditionObject = $this->getCondition();
        }

        if ($limit = $this->getObjectDefinition()->getLimitDataSets()) {
            $conditionObject->mergeAnd($limit);
        }

        if ($extraCondition = $this->getCustomListingCondition()) {
            $conditionObject->mergeAnd($extraCondition);
        }

        if ($this->getPermissionCheck() && $aclCondition = $this->getJarves()->getACL()->getListingCondition($this->getObject())) {
            $conditionObject->mergeAndBegin($aclCondition);
        }

        $options['order'] = $this->getOrder();

        if (is_array($options['fields'])) {
            $options['fields'] = implode(',', $options['fields']);
        }

        $options['fields'] .= ',' . implode(',', array_keys($this->getColumns() ? : array()));
        $options['fields'] .= ',' . implode(',', $this->getTreeFields());

        if ($this->getMultiLanguage()) {

            //does the object have a lang field?
            if ($this->getObjectDefinition()->getField('lang')) {

                //add language condition
                $langCondition = array(
                    array('lang', '=', (string)$this->getRequest()->request->get('lang')),
                    'OR',
                    array('lang', 'IS NULL'),
                );
                $conditionObject->mergeAnd($langCondition);
            }
        }

        $items = $storageController->getBranch($pk, $conditionObject, $depth, $scope, $options);

        if ($withAcl && is_array($items)) {
            foreach ($items as &$item) {
                $this->prepareRow($item);
            }
        }

        return $items;
    }

    /**
     * Returns items count per branch.
     *
     * @param  mixed $pk
     * @param  mixed $scope
     * @param  array $filter
     *
     * @return array
     */
    public function getBranchChildrenCount($pk = null, $scope = null, $filter = null)
    {
        $condition = $this->getCondition();
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());

        if ($pk) {
            $pk = $storageController->normalizePrimaryKey($pk);
        }

        if ($limit = $this->getObjectDefinition()->getLimitDataSets()) {
            $condition->mergeAnd($limit);
        }

        if ($this->getPermissionCheck() && $aclCondition = $this->getJarves()->getACL()->getListingCondition($this->getObject())) {
            $condition->mergeAndBegin($aclCondition);
        }

        if ($filter) {
            $filterCondition = self::buildFilter($filter);
            $condition->mergeAnd($filterCondition);
        }

        if ($extraCondition = $this->getCustomListingCondition()) {
            $condition->mergeAnd($extraCondition);
        }

        return $storageController->getBranchChildrenCount($pk, $condition, $scope);

    }

    /**
     * @param Condition|array $filter
     * @param string $query
     * @return int
     */
    public function getCount($filter, $query = '')
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());

        $condition = new \Jarves\Configuration\Condition(null, $this->getJarves());

        if ($filter && is_array($filter)) {
            $condition->fromPk($filter, $this->getObject());
        } else if ($filter instanceof Condition) {
            $condition = $filter;
        } else {
            $condition = new \Jarves\Configuration\Condition(null, $this->getJarves());
        }

        if ($limit = $this->getObjectDefinition()->getLimitDataSets()) {
            $condition->mergeAnd($limit);
        }

        if ($this->getPermissionCheck() && $aclCondition = $this->getJarves()->getACL()->getListingCondition($this->getObject())) {
            $condition->mergeAndBegin($aclCondition);
        }

        if ($query) {
            if ($queryCondition = $this->getQueryCondition($query, $this->getSelection('', true))) {
                $condition->mergeAnd($queryCondition);
            }
        }

        return $storageController->getCount($condition);
    }

    public function getParent($pk)
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());
        $pk = $storageController->normalizePrimaryKey($pk);

        return $storageController->getParent($pk);
    }

    public function getParents($pk)
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());
        $pk = $storageController->normalizePrimaryKey($pk);

        return $storageController->getParents($pk);
    }

    public function moveItem($pk, $targetPk, $position = 'first', $targetObjectKey = '')
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());

        $sourcePk = $this->getJarves()->getObjects()->normalizePk(
            $this->getObject(),
            $pk
        );

        $targetPk = $this->getJarves()->getObjects()->normalizePk(
            $targetObjectKey ?: $this->getObject(),
            $targetPk
        );

        return $storageController->move($sourcePk, $targetPk, $position, $targetObjectKey);
    }

    public function getRoots($condition = null)
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());

        if (!$this->getObjectDefinition()->isNested()) {
            throw new \Exception('Object is not a nested set.');
        }

        $options['fields'] = $this->getNestedSelection($this->getObjectDefinition()->getNestedRootObjectLabelField());

        if ($this->getObjectDefinition()->getNestedRootAsObject()) {
            return $this->getJarves()->getObjects()->getList($this->getObjectDefinition()->getNestedRootObject(), null, $options);
        } else {
            $conditionObject = $condition ?: new Condition(null, $this->getJarves());

            if ($this->getPermissionCheck() && $aclCondition = $this->getJarves()->getACL()->getListingCondition($this->getObject())) {
                $conditionObject->mergeAndBegin($aclCondition);
            }

            return $storageController->getRoots($conditionObject, $options);
        }
    }

    public function getRoot($scope = null)
    {
        if ($this->getObjectDefinition()->getNestedRootAsObject() && $scope === null) {
            throw new \Exception('No `scope` defined.');
        }

        $options['fields'] = $this->getNestedSelection($this->getObjectDefinition()->getNestedRootObjectLabelField());

        return $this->getJarves()->getObjects()->get($this->getObjectDefinition()->getNestedRootObject(), $scope, $options);
    }

    /**
     * Here you can define additional conditions for all operations (edit/listing).
     *
     * @return \Jarves\Configuration\Condition definition
     */
    public function getCondition()
    {
        return new Condition(null, $this->getJarves());
    }

    /**
     * Here you can define additional conditions for edit operations.
     *
     * @return \Jarves\Configuration\Condition definition
     */
    public function getCustomEditCondition()
    {
    }

    /**
     * Here you can define additional conditions for listing operations.
     *
     * @return \Jarves\Configuration\Condition definition
     */
    public function getCustomListingCondition()
    {
    }

    /**
     *
     * Adds multiple entries.
     *
     * We need as POST following data:
     *
     * {
     *
     *    _items: [
     *         {field1: 'foo', field2: 'bar'},
     *         {field1: 'foo2', field2: 'bar2'},
     *          ....
     *     ],
     *
     *     fixedField1: 'asd',
     *     fixedField2: 'fgh',
     *
     *     _: 'first', //take a look at `$this->getJarves()->getObjects()->add()` at parameter `$pPosition`
     *     _pk: {
     *         id: 123132
     *     },
     *     _targetObjectKey: 'node' //can differ between the actual object and the target (if we have a different object as root,
     *                              //then only position `first` and 'last` are available.)
     *
     *
     * }
     *
     * @return array|mixed
     */
    public function addMultiple(Request $request)
    {
        $inserted = array();

        $fixedFields = $this->getAddMultipleFixedFields();

        $fixedData = array();

        if ($fixedFields) {
            $data = $this->collectData($request);
            $fixedData = $this->mapData($data, $fixedFields);
        }

        $fields = $this->getAddMultipleFields();

        $position = $this->getRequest()->request->get('_position');
        $items = $this->getRequest()->request->get('_items');
        if (!$items) {
            $items = $this->getRequest()->query->get('_items');
        }

        if ($position == 'first' || $position == 'next') {
            $items = array_reverse($items);
        }

        foreach ($items as $item) {

            $data = $fixedData;
            $data += $this->mapData($this->collectData($request), $fields, $item);

            try {
                $inserted[] = $this->add(
                    $data,
                    $this->getRequest()->request->get('_pk'),
                    $position,
                    $this->getRequest()->request->get('_targetObjectKey')
                );
            } catch (\Exception $e) {
                $inserted[] = array('error' => $e);
            }

        }

        return $inserted;

    }

    /**
     * Adds a new item.
     *
     * Data is passed as POST.
     *
     * @param  Request|array $requestOrData
     * @param  array|null $pk
     * @param  string|null $position If nested set. `first` (child), `last` (child), `prev` (sibling), `next` (sibling)
     * @param  string|null $targetObjectKey
     *
     * @return mixed false if some went wrong or a array with the new primary keys.
     *
     * @throws \InvalidArgumentException
     */
    public function add($requestOrData, $pk = null, $position = null, $targetObjectKey = null)
    {
        //collect values
        $targetObjectKey = Objects::normalizeObjectKey($targetObjectKey);
        $values = $this->collectData($requestOrData);
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());

        if ($this->getPermissionCheck()) {
            foreach ($values as $fieldName => $value) {
                //todo, what if $targetObjectKey differs from $objectKey

                if (!$this->getJarves()->getACL()->checkAdd($this->getObject(), $pk, $fieldName)) {
                    unset($values[$fieldName]);
                }
            }
        }

        $args = [
            'pk' => $pk,
            'values' => &$values,
            'controller' => $this,
            'position' => &$position,
            'targetObjectKey' => &$targetObjectKey,
            'mode' => 'add'
        ];
        $eventPre = new GenericEvent($this->getObject(), $args);

        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify-pre', $eventPre);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/add-pre', $eventPre);

        $data = $this->mapData($values);

        if ($targetObjectKey && $targetObjectKey != $this->getObject()) {
            if ($position == 'prev' || $position == 'next') {
                throw new \InvalidArgumentException(
                    sprintf('Its not possible to use `prev` or `next` to add a new entry with a different object key. [target: %s, self: %s]',
                        $targetObjectKey, $this->getObject())
                );
            }

            $targetPk = $this->getJarves()->getObjects()->normalizePk($targetObjectKey, $pk);

            //since propel's nested set behaviour only allows a single value as scope, we need to use the first pk
            $scope = current($targetPk);
            $result = $storageController->add($data, null, $position, $scope);
        } else {
            $result = $storageController->add($data, $pk, $position);
        }

        if ($this->getWithNewsFeed()) {
            $values = array_merge($values, $result);
            $this->getJarves()->getUtils()->newNewsFeed($this->getObject(), $values, 'added');
        }

        $args['result'] = $result;
        $event = new GenericEvent($this->getObject(), $args);

        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify', $event);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/add', $event);

        return $result;
    }

    /**
     * @param $pk
     *
     * @return bool
     */
    public function remove($pk)
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());
        $pk = $storageController->normalizePrimaryKey($pk);
        $this->primaryKey = $pk;

        $args = [
            'pk' => $pk,
            'mode' => 'remove'
        ];
        $eventPre = new GenericEvent($this->getObject(), $args);

        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify-pre', $eventPre);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/remove-pre', $eventPre);

        $item = $this->getItem($pk);

        $result = $storageController->remove($pk);

        $args['result'] = $result;
        $event = new GenericEvent($this->getObject(), $args);

        if ($this->getWithNewsFeed()) {
            $this->getJarves()->getUtils()->newNewsFeed($this->getObject(), $item, 'removed');
        }

        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify', $event);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/remove', $event);

        return $result;
    }

    /**
     * Updates a object entry. This means, all fields which are not defined will be saved as NULL.
     *
     * @param  array $pk
     * @param  Request|array $requestOrData
     *
     * @return bool
     */
    public function update($pk, $requestOrData)
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());
        $pk = $storageController->normalizePrimaryKey($pk);
        $this->primaryKey = $pk;
        $values = $this->collectData($requestOrData);

        $args = [
            'pk' => $pk,
            'values' => &$values,
            'controller' => $this,
            'mode' => 'update'
        ];
        $eventPre = new GenericEvent($this->getObject(), $args);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify-pre', $eventPre);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/update-pre', $eventPre);

        $item = $this->getItem($pk);

        if ($this->getPermissionCheck()) {
            if (!$item) {
                return null;
            }

            if (!$this->getJarves()->getACL()->checkUpdateExact($this->getObject(), $pk)) {
                return null;
            }

            foreach ($values as $fieldName => $value) {
                if (!$this->getJarves()->getACL()->checkUpdateExact($this->getObject(), $pk, [$fieldName => $value])) {
                    unset($values[$fieldName]);
                }
            }
        }

        if (($condition = $this->getCondition()) && $condition->hasRules()) {
            if (!$condition->satisfy($item, $this->getObject())) {
                return null;
            }
        }

        $data = $this->mapData($values);

        if ($this->getWithNewsFeed()) {
            $this->getJarves()->getUtils()->newNewsFeed($this->getObject(), array_merge($values, $pk), 'updated');
        }

        $result = $storageController->update($pk, $data);

        $args['result'] = $result;
        $event = new GenericEvent($this->getObject(), $args);

        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify', $event);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/update', $event);

        return $result;
    }

    /**
     * Patches a object entry. This means, only defined fields will be saved. Fields which are not defined will
     * not be overwritten.
     *
     * @param  Request|array $requestOrData
     * @param  array $pk
     *
     * @return bool
     */
    public function patch($pk, $requestOrData)
    {
        $storageController = $this->getJarves()->getObjects()->getStorageController($this->getObject());
        $pk = $storageController->normalizePrimaryKey($pk);
        $this->primaryKey = $pk;
        $values = $this->collectData($requestOrData);

        $args = [
            'pk' => $pk,
            'values' => &$values,
            'controller' => $this,
            'mode' => 'update'
        ];
        $eventPre = new GenericEvent($this->getObject(), $args);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify-pre', $eventPre);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/patch-pre', $eventPre);

        $item = $this->getItem($pk);

        if ($this->getPermissionCheck()) {
            if (!$item) {
                return null;
            }

            if (!$this->getJarves()->getACL()->checkUpdateExact($this->getObject(), $pk)) {
                return null;
            }

            foreach ($values as $fieldName => $value) {
                if (!$this->getJarves()->getACL()->checkUpdateExact($this->getObject(), $pk, [$fieldName => $value])) {
                    unset($values[$fieldName]);
                }
            }
        }


        if (($condition = $this->getCondition()) && $condition->hasRules()) {
            if (!$condition->satisfy($item, $this->getObject())) {
                return null;
            }
        }

        $incomingFields = $requestOrData instanceof Request ? array_keys($requestOrData->request->all()) : array_keys($requestOrData);
        $changedData = $this->mapData($values, $incomingFields, $item);

        if ($this->getWithNewsFeed()) {
            $this->getJarves()->getUtils()->newNewsFeed($this->getObject(), array_merge($values, $pk), 'updated');
        }
        $result = $storageController->patch($pk, $changedData);

        $args['result'] = $result;
        $event = new GenericEvent($this->getObject(), $args);

        $this->getJarves()->getEventDispatcher()->dispatch('core/object/modify', $event);
        $this->getJarves()->getEventDispatcher()->dispatch('core/object/patch', $event);

        return $result;
    }

    /**
     * @param Request|array $requestOrData
     * @return array
     */
    public function collectData($requestOrData)
    {
        if (!$requestOrData instanceof Request) {
            return $requestOrData;
        }

        $fields = $this->_fields;
        $values = [];

        foreach ($fields as $field) {
            $key = lcfirst($field->getId());

            $value = $requestOrData->files->get($key);
            if (!$value) {
                $value = $requestOrData->request->get($key);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Maps all $data to its field values (Admin\Type*::mapValues)
     * Iterates only through all defined fields in $fields.
     *
     * @param  array $data
     * @param  \Jarves\Configuration\Field[] $fields The fields definition. If empty we use $this->fields.
     * @param  mixed $defaultData Default data. Is used if a field is not defined through _POST or _GET
     *
     * @return array
     * @throws \Jarves\Exceptions\InvalidFieldValueException
     */
    public function mapData(array $data, array $fieldsToReturn = null, $defaultData = null)
    {
        $values = array();

        $fields = $this->_fields;

        if ($fieldsToReturn) {
            $fieldsToReturn = array_flip($fieldsToReturn); //flip keys so the check is faster
        }

        if ($this->getMultiLanguage()) {
            $langField = new Field(null, $this->getJarves());
            $langField->setId('lang');
            $langField->setType('text');
            $langField->setRequired(true);
            $fields[] = $langField;
        }

        new \Jarves\Admin\Form\Form($fields);

        foreach ($fields as $field) {
            $key = lcfirst($field->getId());
            $value = @$data[$key];
            if (null == $value && $defaultData) {
                $value = @$defaultData[$key];
            }

            if ($field['customValue'] && method_exists($this, $method = $field['customValue'])) {
                $value = $this->$method($field, $key);
            }

            $field->setValue($value);
        }

        foreach ($fields as $field) {
            $key = $field->getId();
            if ($field['noSave']) {
                continue;
            }

            if ($field->getSaveOnlyFilled() && ($field->getValue() === '' || $field->getValue() === null)) {
                continue;
            }

            if ($field->getCustomSave() && method_exists($this, $method = $field->getCustomSave())) {
                $this->$method($values, $values, $field);
                continue;
            }

            if (!$fieldsToReturn || isset($fieldsToReturn[$key])) {
                if (!$errors = $field->validate()) {
                    $field->mapValues($values);
                } else {
                    $restException = new ValidationFailedException(sprintf(
                        'Field `%s` has a invalid value.',
                        $key
                    ), 420);
                    $restException->setData(['fields' => [$field->getId() => $errors]]);
                    throw $restException;
                }
            }
        }

        return $values;
    }

    /**
     * Each item goes through this function in getItems(). Defines whether a item is editable or deleteable.
     * You can attach here extra action icons, too.
     *
     * Result should be:
     *
     * $item['_editable'] = true|false
     * $item['_deleteable'] = true|false
     * $item['_actions'] = array(
     *         array('/* action * /') //todo
     *     )
     * )
     *
     * @param array $item
     *
     * @return array
     */
    public function prepareRow(&$item)
    {
        $item['_editable'] = $this->getJarves()->getACL()->isUpdatable($this->getObject(), $item);
        $item['_deletable'] = $this->getJarves()->getACL()->isDeletable($this->getObject(), $item);
    }

    /**
     *
     * The primary key of the current object.
     * If the class created a item through addItem(),
     * it contains the primary key of the newly created
     * item.
     *
     * array(
     *    'id' => 1234
     * )
     *
     * array(
     *     'id' => 1234,
     *     'subId' => 5678
     * )
     *
     * @return array
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @return bool
     */
    public function getPermissionCheck()
    {
        return $this->permissionCheck;
    }

    /**
     * @param boolean $permissionCheck
     */
    public function setPermissionCheck($permissionCheck)
    {
        $this->permissionCheck = $permissionCheck;
    }

    /**
     * @param boolean $add
     */
    public function setAdd($add)
    {
        $this->add = $add;
    }

    /**
     * @return boolean
     */
    public function getAdd()
    {
        return $this->add;
    }

    /**
     * @param string $addEntrypoint
     */
    public function setAddEntrypoint($addEntrypoint)
    {
        $this->addEntrypoint = $addEntrypoint;
    }

    /**
     * @return string
     */
    public function getAddEntrypoint()
    {
        return $this->addEntrypoint;
    }

    /**
     * @param string $addIcon
     */
    public function setAddIcon($addIcon)
    {
        $this->addIcon = $addIcon;
    }

    /**
     * @return string
     */
    public function getAddIcon()
    {
        return $this->addIcon;
    }

    /**
     * @param string $customOrderBy
     */
    public function setCustomOrderBy($customOrderBy)
    {
        $this->customOrderBy = $customOrderBy;
    }

    /**
     * @return string
     */
    public function getCustomOrderBy()
    {
        return $this->customOrderBy;
    }

    /**
     * @param string $customOrderByDirection
     */
    public function setCustomOrderByDirection($customOrderByDirection)
    {
        $this->customOrderByDirection = $customOrderByDirection;
    }

    /**
     * @return string
     */
    public function getCustomOrderByDirection()
    {
        return $this->customOrderByDirection;
    }

    /**
     * @param boolean $domainDepended
     */
    public function setDomainDepended($domainDepended)
    {
        $this->domainDepended = $domainDepended;
    }

    /**
     * @return boolean
     */
    public function getDomainDepended()
    {
        return $this->domainDepended;
    }

    /**
     * @param boolean $edit
     */
    public function setEdit($edit)
    {
        $this->edit = $edit;
    }

    /**
     * @return boolean
     */
    public function getEdit()
    {
        return $this->edit;
    }

    /**
     * @param string $editEntrypoint
     */
    public function setEditEntrypoint($editEntrypoint)
    {
        $this->editEntrypoint = $editEntrypoint;
    }

    /**
     * @return string
     */
    public function getEditEntrypoint()
    {
        return $this->editEntrypoint;
    }

    /**
     * @param string $editIcon
     */
    public function setEditIcon($editIcon)
    {
        $this->editIcon = $editIcon;
    }

    /**
     * @return string
     */
    public function getEditIcon()
    {
        return $this->editIcon;
    }

    /**
     * @param array $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
        $this->_fields = array();
        $this->prepareFieldItem($this->fields);
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $filter
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * @param array $columns
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @return array
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param int $defaultLimit
     */
    public function setDefaultLimit($defaultLimit)
    {
        $this->defaultLimit = $defaultLimit;
    }

    /**
     * @return int
     */
    public function getDefaultLimit()
    {
        return $this->defaultLimit;
    }

    /**
     * @param boolean $multiLanguage
     */
    public function setMultiLanguage($multiLanguage)
    {
        $this->multiLanguage = $multiLanguage;
    }

    /**
     * @return boolean
     */
    public function getMultiLanguage()
    {
        return $this->multiLanguage;
    }

    /**
     * @param string $object
     */
    public function setObject($object)
    {
        $this->object = $object;
    }

    /**
     * @return string
     */
    public function getObject()
    {
        return Objects::normalizeObjectKey($this->object);
    }

    /**
     * @param array $objectDefinition
     */
    public function setObjectDefinition($objectDefinition)
    {
        $this->objectDefinition = $objectDefinition;
    }

    /**
     * @return \Jarves\Configuration\Object
     */
    public function getObjectDefinition()
    {
        return $this->objectDefinition;
    }

    /**
     * @param array $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @return array
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param boolean $remove
     */
    public function setRemove($remove)
    {
        $this->remove = $remove;
    }

    /**
     * @return boolean
     */
    public function getRemove()
    {
        return $this->remove;
    }

    /**
     * @param string $removeIcon
     */
    public function setRemoveIcon($removeIcon)
    {
        $this->removeIcon = $removeIcon;
    }

    /**
     * @return string
     */
    public function getRemoveIcon()
    {
        return $this->removeIcon;
    }

    /**
     * @param string $removeIconItem
     */
    public function setRemoveIconItem($removeIconItem)
    {
        $this->removeIconItem = $removeIconItem;
    }

    /**
     * @return string
     */
    public function getRemoveIconItem()
    {
        return $this->removeIconItem;
    }

    /**
     * @param string $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param boolean $workspace
     */
    public function setWorkspace($workspace)
    {
        $this->workspace = $workspace;
    }

    /**
     * @return boolean
     */
    public function getWorkspace()
    {
        return $this->workspace;
    }

    public function setFilterFields($filterFields)
    {
        $this->filterFields = $filterFields;
    }

    public function getFilterFields()
    {
        return $this->filterFields;
    }

    public function setItemLayout($itemLayout)
    {
        $this->itemLayout = $itemLayout;
    }

    public function getItemLayout()
    {
        return $this->itemLayout;
    }

    /**
     * @param array $primary
     */
    public function setPrimary($primary)
    {
        $this->primary = $primary;
    }

    /**
     * @return array
     */
    public function getPrimary()
    {
        return $this->primary;
    }

    /**
     * @param boolean $asNested
     */
    public function setAsNested($asNested)
    {
        $this->asNested = $asNested;
    }

    /**
     * @return boolean
     */
    public function getAsNested()
    {
        return $this->asNested;
    }

    public function setNestedMove($nestedMove)
    {
        $this->nestedMove = $nestedMove;
    }

    public function getNestedMove()
    {
        return $this->nestedMove;
    }

    public function setNestedRootAdd($nestedRootAdd)
    {
        $this->nestedRootAdd = $nestedRootAdd;
    }

    public function getNestedRootAdd()
    {
        return $this->nestedRootAdd;
    }

    public function setNestedRootAddEntrypoint($nestedRootAddEntrypoint)
    {
        $this->nestedRootAddEntrypoint = $nestedRootAddEntrypoint;
    }

    public function getNestedRootAddEntrypoint()
    {
        return $this->nestedRootAddEntrypoint;
    }

    public function setNestedRootAddIcon($nestedRootAddIcon)
    {
        $this->nestedRootAddIcon = $nestedRootAddIcon;
    }

    public function getNestedRootAddIcon()
    {
        return $this->nestedRootAddIcon;
    }

    public function setNestedRootAddLabel($nestedRootAddLabel)
    {
        $this->nestedRootAddLabel = $nestedRootAddLabel;
    }

    public function getNestedRootAddLabel()
    {
        return $this->nestedRootAddLabel;
    }

    public function setNestedRootEdit($nestedRootEdit)
    {
        $this->nestedRootEdit = $nestedRootEdit;
    }

    public function getNestedRootEdit()
    {
        return $this->nestedRootEdit;
    }

    public function setNestedRootEditEntrypoint($nestedRootEditEntrypoint)
    {
        $this->nestedRootEditEntrypoint = $nestedRootEditEntrypoint;
    }

    public function getNestedRootEditEntrypoint()
    {
        return $this->nestedRootEditEntrypoint;
    }

    public function setNestedRootRemove($nestedRootRemove)
    {
        $this->nestedRootRemove = $nestedRootRemove;
    }

    public function getNestedRootRemove()
    {
        return $this->nestedRootRemove;
    }

    public function setNestedRootRemoveEntrypoint($nestedRootRemoveEntrypoint)
    {
        $this->nestedRootRemoveEntrypoint = $nestedRootRemoveEntrypoint;
    }

    public function getNestedRootRemoveEntrypoint()
    {
        return $this->nestedRootRemoveEntrypoint;
    }

    public function setNestedMoveable($nestedMoveable)
    {
        $this->nestedMoveable = $nestedMoveable;
    }

    public function getNestedMoveable()
    {
        return $this->nestedMoveable;
    }

    public function setNestedAddWithPositionSelection($nestedAddWithPositionSelection)
    {
        $this->nestedAddWithPositionSelection = $nestedAddWithPositionSelection;
    }

    public function getNestedAddWithPositionSelection()
    {
        return $this->nestedAddWithPositionSelection;
    }

    public function setNewLabel($newLabel)
    {
        $this->newLabel = $newLabel;
    }

    public function getNewLabel()
    {
        return $this->newLabel;
    }

    public function setRemoveEntrypoint($removeEntrypoint)
    {
        $this->removeEntrypoint = $removeEntrypoint;
    }

    public function getRemoveEntrypoint()
    {
        return $this->removeEntrypoint;
    }

    public function setAddMultiple($addMultiple)
    {
        $this->addMultiple = $addMultiple;
    }

    public function getAddMultiple()
    {
        return $this->addMultiple;
    }

    public function setAddMultipleFieldContainerWidth($addMultipleFieldContainerWidth)
    {
        $this->addMultipleFieldContainerWidth = $addMultipleFieldContainerWidth;
    }

    public function getAddMultipleFieldContainerWidth()
    {
        return $this->addMultipleFieldContainerWidth;
    }

    public function setAddMultipleFields($addMultipleFields)
    {
        $this->addMultipleFields = $addMultipleFields;
    }

    public function getAddMultipleFields()
    {
        return $this->addMultipleFields;
    }

    public function setAddMultipleFixedFields($addMultipleFixedFields)
    {
        $this->addMultipleFixedFields = $addMultipleFixedFields;
    }

    public function getAddMultipleFixedFields()
    {
        return $this->addMultipleFixedFields;
    }

    /**
     * @param boolean $allowCustomSelectFields
     */
    public function setAllowCustomSelectFields($allowCustomSelectFields)
    {
        $this->allowCustomSelectFields = $allowCustomSelectFields;
    }

    /**
     * @return boolean
     */
    public function getAllowCustomSelectFields()
    {
        return $this->allowCustomSelectFields;
    }

    /**
     * @param int $itemsPerPage
     */
    public function setItemsPerPage($itemsPerPage)
    {
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->itemsPerPage;
    }

    /**
     * @param boolean $usePatch
     */
    public function setUsePatch($usePatch)
    {
        $this->usePatch = $usePatch;
    }

    /**
     * @return boolean
     */
    public function getUsePatch()
    {
        return $this->usePatch;
    }

    /**
     * @param boolean $startCombine
     */
    public function setStartCombine($startCombine)
    {
        $this->startCombine = $startCombine;
    }

    /**
     * @return boolean
     */
    public function getStartCombine()
    {
        return $this->startCombine;
    }

}