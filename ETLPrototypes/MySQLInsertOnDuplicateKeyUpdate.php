<?php
namespace axenox\ETL\ETLPrototypes;

use exface\Core\Exceptions\RuntimeException;
use axenox\ETL\Interfaces\ETLStepResultInterface;
use axenox\ETL\Common\Traits\SqlColumnMappingsTrait;
use axenox\ETL\Common\Traits\SqlIncrementalWhereTrait;

class MySQLInsertOnDuplicateKeyUpdate extends SQLRunner
{   
    use SqlColumnMappingsTrait;
    use SqlIncrementalWhereTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \axenox\ETL\ETLPrototypes\MySQLReplace::getSql()
     */
    protected function getSql() : string
    {
        if ($customSql = parent::getSql()) {
            return $customSql;
        }
        
        return <<<SQL

INSERT INTO [#to_object_address#]
        ([#insert_columns#]) 
    (SELECT 
        [#insert_selects#]
        FROM [#from_object_address#] [#source#]
        WHERE [#incremental_where#]
    )
    ON DUPLICATE KEY UPDATE 
        [#update_pairs#];

SQL;
    }
    
    /**
     * Override the default INSERT statement to add a GROUP BY or other enhancements.
     *
     * The default statement is
     *
     * ```
     * INSERT INTO [#to_object_address#]
     *        ([#insert_columns#]) 
     *    (SELECT 
     *        [#insert_selects#]
     *        FROM [#from_object_address#] [#source#]
     *        WHERE [#incremental_where#]
     *    )
     *    ON DUPLICATE KEY UPDATE 
     *        [#update_pairs#];
     *
     * ```
     * 
     * @uxon-property sql
     * @uxon-type string
     * @uxon-template INSERT INTO [#to_object_address#]\n        ([#insert_columns#]) \n    (SELECT \n        [#insert_selects#]\n        FROM [#from_object_address#] [#source#]\n        WHERE [#incremental_where#]\n    )\n    ON DUPLICATE KEY UPDATE \n        [#update_pairs#];
     *
     * @see \axenox\ETL\ETLPrototypes\SQLRunner::setSql()
     */
    protected function setSql(string $value) : SQLRunner
    {
        return parent::setSql($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \axenox\ETL\ETLPrototypes\SQLRunner::getPlaceholders()
     */
    protected function getPlaceholders(string $stepRunUid, ETLStepResultInterface $lastResult = null) : array
    {
        $insertSelects = '';
        $insertCols = '';
        $updates = '';
        
        foreach ($this->getColumnMappings() as $map) {
            $insertCols .= ($insertCols ? ', ' : '') . $map->getToSql();
            $insertSelects .= ($insertSelects ? ', ' : '') . "[#source#].{$map->getFromSql()}";
            $updates .= ($updates ? ', ' : '') . "{$map->getToSql()} = [#source#].{$map->getFromSql()}";
        }
        
        if ($insertSelects === '' || $insertCols === '') {
            throw new RuntimeException('Cannot run ETL step "' . $this->getName() . '": no `column_mappings` defined!');
        }
        
        if ($runUidAlias = $this->getStepRunUidAttributeAlias()) {
            $toSql = $this->getToObject()->getAttribute($runUidAlias)->getDataAddress();
            $insertCols .= ', ' . $toSql;
            $insertSelects .= ', [#step_run_uid#]';
            $updates .= ($updates ? ', ' : '') . "{$toSql} = [#step_run_uid#]";
        }
        
        return array_merge(parent::getPlaceholders($stepRunUid, $lastResult), [
            'source' => 'exfsrc',
            'update_pairs' => $updates,
            'insert_columns' => $insertCols,
            'insert_selects' => $insertSelects,
            'incremental_where' => $this->getSqlIncrementalWhere() ?? '(1=1)'
        ]);
    }
}