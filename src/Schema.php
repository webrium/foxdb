<?php

namespace Foxdb;

use Foxdb\DB;

class Schema
{
    private string $table;
    private array $fields = [];
    private array $field_query = [];
    private bool $field_query_in_process = false;
    private string $change_action = '';

    const INDEX_UNIQUE = 'UNIQUE';
    const INDEX_PRIMARY = 'PRIMARY';
    const INDEX = 'INDEX';

    /**
     * Constructor method to set the table name.
     *
     * @param string $table
     */
    public function __construct($table)
    {
        $this->table = $table;
    }


    private function initNewFieldQuery()
    {

        if ($this->field_query_in_process) {
            $this->fields[] = $this->field_query;
        }

        $this->field_query_in_process = true;

        $this->resetFieldQuery();
    }

    private function resetFieldQuery()
    {
        $this->field_query = [
            'Action' => '',
            'Field' => '',
            'Type' => '',
            'Collation' => '',
            'Null' => 'NOT NULL',
            'Default' => '',
            'Extra' => '',
            'Position' => ''
        ];
    }

    private function setFieldQuery(string $attribute, $value)
    {
        if (in_array($attribute, ['Action', 'Field', 'Type', 'Collation', 'Null', 'Default', 'Extra', 'Position']) == false) {
            throw new \Exception("The field attribute name is not valid", 1);
        }

        $this->field_query[$attribute] = $value;
    }

    // private function getFieldQueryString()
    // {
    //     return trim(implode(' ', $this->field_query));
    // }

    private function addToFieldsAndResetFieldQuery()
    {
        // $string = $this->getFieldQueryString();
        // if (empty($string) == false) {
        $this->fields[] = $this->field_query;
        // }
        $this->resetFieldQuery();
    }


    private function reset()
    {
        $this->fields = [];
        $this->change_action = '';
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function id($name = 'id')
    {
        $this->increments($name);
        return $this;
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function bigID($name = 'id')
    {
        return $this->increments($name, 'BIGINT(20)');
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function increments($name = 'id', $type = 'INT(11)')
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "$type UNSIGNED");
        $this->setFieldQuery('Extra', 'AUTO_INCREMENT PRIMARY KEY');

        return $this;
    }


    /**
     * Adds a boolean field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function boolean($name)
    {
        $this->tinyInt($name, 1);
        $this->default(0);

        return $this;
    }


    /**
     * Adds a tiny int field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function tinyInt($name, $length = 4)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TINYINT($length)");

        return $this;
    }

    /**
     * Adds a medium int field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function mediumInt($name, $length = 9)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "MEDIUMINT($length)");

        return $this;
    }


    /**
     * Adds a small int field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function smallInt($name, $length = 6)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "SMALLINT($length)");

        return $this;
    }


    /**
     * Add an integer column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function integer($name, $length = 11)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "INT($length)");
        return $this;
    }

    /**
     * Add an big integer column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function bigInt($name, $length = 20)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "BIGINT($length)");

        return $this;
    }

    /**
     * Add a string column to the table.
     *
     * @param string $name
     * @param int $length
     * @return $this
     */
    public function string($name, $length = 255)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "VARCHAR($length)");

        return $this;
    }


    /**
     * Add a tiny text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function tinyText($name)
    {

        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TINYTEXT");

        return $this;
    }

    /**
     * Add a text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function text($name)
    {

        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TEXT");

        return $this;
    }


    /**
     * Add a medium text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function mediumText($name)
    {

        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "MEDIUMTEXT");

        return $this;
    }


    /**
     * Add a long text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function longText($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "LONGTEXT");

        return $this;
    }

    /**
     * Add a JSON column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function json($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "JSON");

        return $this;
    }

    /**
     * Add created_at and updated_at timestamp columns to the table.
     *
     * @return $this
     */
    public function timestamps()
    {
        $this->dateTime('created_at')->default('CURRENT_TIMESTAMP', true);
        $this->timestamp('updated_at')->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', true);

        // $this->initNewFieldQuery();
        // $this->setFieldQuery('Field', "`created_at`");
        // $this->setFieldQuery('Type', "DATETIME");
        // $this->setFieldQuery('Default', "DEFAULT CURRENT_TIMESTAMP");

        // $this->initNewFieldQuery();
        // $this->setFieldQuery('Field', "`updated_at`");
        // $this->setFieldQuery('Type', "TIMESTAMP");
        // $this->setFieldQuery('Default', "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        // $this->fields[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        // $this->fields[] = "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }


    /**
     * Add a dateTime column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function dateTime($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "DATETIME");

        return $this;
    }


    /**
     * Add a dateTime column to the table.
     *
     * @param string $name
     * @return $this
     */
    private function timestamp($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TIMESTAMP");

        return $this;
    }





    /**
     * Add a time column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function time($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TIME");

        return $this;
    }

    /**
     * Add a date column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function date($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "DATE");

        return $this;
    }

    /**
     * Add a float column to the table.
     *
     * @param string $name
     * @param string $length
     * @return $this
     */
    public function float($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "FLOAT");

        return $this;
    }

    /**
     * Add a year column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function year($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', 'YEAR(4)');

        return $this;
    }


    /**
     * Add a double column to the table.
     *
     * @param string $name
     * @param string $length
     * @return $this
     */
    public function double($name)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', 'DOUBLE');

        return $this;
    }

    /**
     * Add an enum column to the table.
     *
     * @param string $name
     * @param array $values
     * @return $this
     */
    public function enum($name, array $values)
    {
        $this->initNewFieldQuery();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "ENUM('" . implode("','", $values) . "')");

        return $this;
    }




    /**
     * Set a default value for the last column added to the table.
     *
     * @param string $value
     * @return $this
     */
    public function default($value, $directly = false)
    {
        if ($directly) {
            $this->setFieldQuery('Default', "DEFAULT $value");
        } else {
            $this->setFieldQuery('Default', "DEFAULT '$value'");
        }
        return $this;
    }

    /**
     * Set the last column added to the table as nullable.
     *
     * @return $this
     */
    public function nullable()
    {
        $this->setFieldQuery('Null', "NULL");
        return $this;
    }





    private function generateFieldQueryString(): string
    {
        if (empty($this->change_action) == false) {
            $this->setActionToAllFieldQuerys($this->change_action);
        }

        foreach ($this->fields as &$field) {
            $field = implode(' ', $field);
        }

        return implode(',', $this->fields);
    }


    private function setActionToAllFieldQuerys(string $action): void
    {

        foreach ($this->fields as &$field) {
            $field['Action'] = $action;
        }
    }

    /**
     * Generate a DROP TABLE query for the current table.
     *
     * @return string
     */
    public function drop()
    {
        return DB::query("DROP TABLE IF EXISTS `{$this->table}`;");
    }



    /**
     * Add a new column to the table.
     *
     * @param string $name
     * @param string $type
     * @param string|null $after
     * @return $this
     */
    public function addColumn()
    {
        $this->change_action = 'ADD IF NOT EXISTS';
        return $this;
    }


    /**
     * Drop a column from the table.
     *
     * @param string $name
     * @return $this
     */
    public function dropColumn($name)
    {
        $this->change_action = "DROP COLUMN IF EXISTS `$name`";
        return $this;
    }

    /**
     * Rename a column in the table.
     *
     * @param string $name
     * @param string $new_name
     * @param string $type
     * @return $this
     */
    public function renameColumn($current_name)
    {
        // $this->setFieldQuery('Action', "CHANGE IF EXISTS `$current_name`");
        $this->change_action = "CHANGE IF EXISTS `$current_name`";
        return $this;
    }


    public function after($column_name)
    {
        $this->setFieldQuery('Position', "AFTER `$column_name`");
        return $this;
    }


        /**
     * Drop an index from the table.
     *
     * @param string $name
     * @return $this
     */
    public function dropIndex($name)
    {
        $this->setFieldQuery('Action', 'DROP INDEX');
        $this->setFieldQuery('Field', "`$name`");
        // $this->fields[] = " ";

        return $this;
    }

    /**
     * Add a foreign key constraint to the table.
     *
     * @param string $name
     * @param string $column
     * @param string $table
     * @param string $references
     * @param string $onDelete
     * @param string $onUpdate
     * @return $this
     */
    public function addForeign($name, $column, $table, $references, $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
    {
        $this->fields[] = "CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$table` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
        return $this;
    }

    /**
     * Drop a foreign key constraint from the table.
     *
     * @param string $name
     * @return $this
     */
    public function dropForeign($name)
    {
        $this->fields[] = "DROP FOREIGN KEY `$name`";
        return $this;
    }


    /**
     * Set the character set and collation of the last added column in the $fields array to utf8mb4.
     *
     * @param string $collation The collation to use. Default value is utf8mb4_unicode_ci.
     * @return Schema The current instance of the Schema class.
     */
    public function utf8mb4($collation = 'utf8mb4_unicode_ci')
    {
        $this->setFieldQuery('Collation', "CHARACTER SET utf8mb4 COLLATE $collation");
        return $this;
    }


    /**
     * Set the character set and collation of the last added column in the $fields array to utf8.
     *
     * @param string $collation The collation to use. Default value is utf8_unicode_ci.
     * @return Schema The current instance of the Schema class.
     */
    public function utf8($collation = 'utf8_unicode_ci')
    {
        $this->setFieldQuery('Collation', "CHARACTER SET utf8 COLLATE $collation");
        return $this;
    }

    /**
     * Modify a column in the table.
     *
     * @param string $name
     * @param string $type
     * @return $this
     */
    public function modifyColumn()
    {
        $this->change_action = 'MODIFY COLUMN';
        return $this;
    }

    /**
     * Add an index to the table.
     *
     * @param string $name
     * @param array $columns
     * @param string $type
     * @return $this
     */
    public function addIndex($name, array $columns, $type = 'INDEX')
    {

        $this->setFieldQuery('Action', "ADD $type");
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Extra', "(" . implode(', ', $columns) . ")");
        $this->setFieldQuery('Null', '');

        return $this;
    }





    /**
     * Generate a CREATE TABLE query for the current table and fields.
     *
     * @return string
     */
    public function create($engine = 'InnoDB', $charset = 'utf8mb4', $collate = 'utf8mb4_unicode_ci')
    {
        $this->addToFieldsAndResetFieldQuery();

        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (";
        $sql .= $this->generateFieldQueryString();
        $sql .= ") ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate};";

        $this->reset();
        return DB::query($sql);
    }

    /**
     * Generate an ALTER TABLE query for the current table and fields.
     *
     * @return string
     */
    public function change()
    {
        $this->addToFieldsAndResetFieldQuery();
        $sql = "ALTER TABLE `{$this->table}` ";
        $sql .= $this->generateFieldQueryString();

        $this->reset();
        return DB::query($sql);
    }
}
