<?php
/*
 * LiveStreet CMS
 * Copyright © 2013 OOO "ЛС-СОФТ"
 *
 * ------------------------------------------------------
 *
 * Official site: www.livestreetcms.com
 * Contact e-mail: office@livestreetcms.com
 *
 * GNU General Public License, version 2:
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * ------------------------------------------------------
 *
 * @link http://www.livestreetcms.com
 * @copyright 2013 OOO "ЛС-СОФТ"
 * @author Oleg Demidov
 *
 */

class PluginSeo_ModuleSeo extends ModuleORM
{
    protected $aVars = [];

    public function Init() {
        parent::Init();
    }
    
    /**
     *  Добавить правило для отображения тегов в определенном Эвенте 
     * 
     * @param string $sName   Имя Правила для отображения в списке
     * @param string $sEventName Имя Эвента
     */
    public function AddRule( string $sName, string $sEventName ) 
    {
        
        $rule = Engine::GetEntity('PluginSeo_Seo_Rule');
        
        $rule->setName($sName);
        $rule->setEvent($sEventName);
        
        $rule->Save();
        
    }
    
    public function SetVars(array $aVars) {
        $this->aVars = $aVars;
    }
    
    public function SetVar($key, $var) {
        $this->aVars[$key] = $var;
    }
    
    public function GetVars() {
        return $this->aVars;
    }
    
    public function GetVar($key) {
        if(isset($this->aVars[$key])){
            return $this->aVars[$key];
        }
        return null;
    }
   
    public function ValidateEntitySeo(Entity $entity, Behavior $behavior, $aSeo) {

        if($behavior->getParam('required') and !$aSeo){
            return $this->Lang_Get('plugin.seo.validate.not_fond_seo');
        }
        
        if(!isset($aSeo['keys']) or !isset($aSeo['vals'])){
            return $this->Lang_Get('plugin.seo.validate.not_fond_seo');
        }
        
        $aSeoVars = [];
        foreach ($aSeo['keys'] as $key => $value) 
        {
            $aSeoVars[$value] = $aSeo['vals'][$key];
        }
        
        $entity->_setData(['_seo_for_save' => $aSeoVars]);
        
        return true;
    }
    
    public function GetEntityData(Entity $oEntity, string $sTargetType)
    {
        $data = $oEntity->_getDataOne('_seo_data');
        if (is_null($data)) {
            $this->AttachSeoDataForTargetItems($oEntity, $sTargetType);

            return $oEntity->_getDataOne('_seo_data');
        }
        return $data;
    }
    
    public function RewriteFilter(array $aFilter, Behavior $behavior, $sEntityFull) {

        $oEntitySample = Engine::GetEntity($sEntityFull);

        if (!isset($aFilter['#join'])) {
            $aFilter['#join'] = array();
        }

        if (!isset($aFilter['#select'])) {
            $aFilter['#select'] = array();
        }

        if (array_key_exists("#seo", $aFilter)) {
            $aSeo = [];
                    
            $sJoin = "JOIN " . Config::Get('db.table.seo_seo_data') . " seo ON
					t.`{$oEntitySample->_getPrimaryKey()}` = seo.target_id and
					seo.target_type = '{$behavior->getParam('target_type')}'";
                                        
            foreach ($aFilter["#seo"] as $key => $value) 
            {
                $sJoin .= " and seo.{$key} = ?";
                $aSeo[$key] = $value;
            }
                                        
            $aFilter['#join'][$sJoin] = $aSeo;
            
        }
        
        return $aFilter;
    }
    
    public function RewriteGetItemsByFilter( $aResult, Behavior $behavior, array $aFilter)
    {
        if (!$aResult) {
            return;
        }
        
        if(!is_array($aResult)){
            $aResult = [$aResult];
        }
        /**
         * Список на входе может быть двух видов:
         * 1 - одномерный массив
         * 2 - двумерный, если применялась группировка (использование '#index-group')
         *
         * Поэтому сначала сформируем линейный список
         */
        if (isset($aFilter['#index-group']) and $aFilter['#index-group']) {
            $aEntitiesWork = array();
            foreach ($aResult as $aItems) {
                foreach ($aItems as $oItem) {
                    $aEntitiesWork[] = $oItem;
                }
            }
        } else {
            $aEntitiesWork = $aResult;
        }

        if (!$aEntitiesWork) {
            return;
        }
        /**
         * Цепляем SEO переменные к объектам а так же загружаем их в модуль SEO
         */
        if (isset($aFilter['#with']) and is_array($aFilter['#with']) and in_array('#seo',$aFilter['#with'])) {       
            $this->AttachSeoDataForTargetItems($aEntitiesWork, $behavior->getParam('target_type'));
        }
        
    }
    
    public function AttachSeoDataForTargetItems($aEntityItems, $sTargetType)
    {
        if (!is_array($aEntityItems)) {
            $aEntityItems = array($aEntityItems);
        }
        $aEntitiesId = array(0);
        foreach ($aEntityItems as $oEntity) {
            $aEntitiesId[] = $oEntity->getId();
        }
        /**
         * Получаем таргеты для всех объектов
         */
        
        $aSeo = $this->GetDataItemsByFilter([
            'target_id in' => $aEntitiesId,
            'target_type' => $sTargetType,
            '#index-from-primary'
        ]);
        
        foreach ($aSeo as $data) 
        {
            $this->SetVars(array_merge($this->GetVars(),$data->getVars()));
        }
        
        /**
         * Собираем данные
         */
        foreach ($aEntityItems as $oEntity) {
            if (isset($aSeo[$oEntity->_getPrimaryKeyValue()])) {
                $oEntity->_setData(array('_seo_data' => $aSeo[$oEntity->_getPrimaryKeyValue()]));
            }
        }
    }
    
    public function RemoveSeo(Entity $oEntity, Behavior $oBehavior) {
        if($oData = $this->GetDataByFilter([
            'target_type' => $oBehavior->getParam('target_type'),
            'target_id' => $oEntity->getId()
            ]))
        {
            $oData->Delete();
        }
    }
    
    public function SaveSeo(Entity $oEntity, Behavior $oBehavior) 
    {
        
        $oData = $this->PluginSeo_Seo_GetDataByFilter([
            'target_type' => $oBehavior->getParam('target_type'),
            'target_id' => $oEntity->getId()
        ]);
        
        if (!$oData) {
            $oData = Engine::GetEntity('PluginSeo_Seo_Data');
        }

        $oData->setVars($oEntity->_getDataOne('_seo_for_save'));       
        
        $oData->setTargetId($oEntity->getId());
        $oData->setTargetType($oBehavior->getParam('target_type'));
        
        return $oData->Save();
    }
    
    public function ReplaceVars( $sText ) 
    {
        $aVars = $this->GetVars();
        
        foreach ($aVars as $key => $value) {     
            $sText = str_replace('{$'.$key.'}', $value, $sText);
        } 
        
        $sText = preg_replace('/\{\$\w{1,30}\}/i', '', $sText);
        
        return $sText;
    }
    
    public function GetAllTargetVars($sTargetType = null) {
        $aFilter = [];
        
        if ($sTargetType) {
            $aFilter['target_type'] = $sTargetType;
        }
        
        $aData = $this->PluginSeo_Seo_GetDataItemsByFilter($aFilter);
        
        $aVars = [];
        
        foreach ($aData as $data){
            $aVars = array_merge($aVars, $data->getVars());
        }
        
        return array_keys($aVars);
    }
}
