<?php
namespace Smartex\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class MagentoStorage implements \Smartex\Storage\StorageInterface {
	/**
	 * @var array
	 */
	protected $_keys;

	/**
	 * @inheritdoc
	 */
	public function persist(\Smartex\KeyInterface $key) {
		$this -> _keys[$key -> getId()] = $key;

		$data = serialize($key);

		$encryptedData = \Magento\Framework\App\ObjectManager::getInstance() -> get('\Magento\Framework\Encryption\EncryptorInterface') -> encrypt($data);
		$config = \Magento\Framework\App\ObjectManager::getInstance() -> get('\Magento\Config\Model\ResourceModel\Config');
		$helper = \Magento\Framework\App\ObjectManager::getInstance() -> get('\Smartex\Core\Helper\Data');

		if (true === isset($config) && false === empty($config)) {
			$config->saveConfig($key -> getId(), $encryptedData, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
		} else {
			$helper->debugData('[ERROR] In file lib/Smartex/Storage/MagentoStorage.php, class MagentoStorage::persist - Could not instantiate a \Mage_Core_Model_Config object.');
			throw new \Exception('[ERROR] In file lib/Smartex/Storage/MagentoStorage.php, class MagentoStorage::persist - Could not instantiate a \Mage_Core_Model_Config object.');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function load($id) {
		if (true === isset($id) && true === isset($this -> _keys[$id])) {
			return $this -> _keys[$id];
		}
		$helper = \Magento\Framework\App\ObjectManager::getInstance() -> get('\Smartex\Core\Helper\Data');
		$storeconfig = \Magento\Framework\App\ObjectManager::getInstance() -> get('\Magento\Framework\App\Config\ScopeConfigInterface');
		$entity = $storeconfig -> getValue($id);

		/**
		 * Not in database
		 */
		if (false === isset($entity) || true === empty($entity)) {
			$helper -> debugData('[INFO] Call to MagentoStorage::load($id) with the id of ' . $id . ' did not return the store config parameter because it was not found in the database.');
			throw new \Exception('[INFO] Call to MagentoStorage::load($id) with the id of ' . $id . ' did not return the store config parameter because it was not found in the database.');
		}

		$decodedEntity = unserialize(\Magento\Framework\App\ObjectManager::getInstance() -> get('\Magento\Framework\Encryption\EncryptorInterface') -> decrypt($entity));

		if (false === isset($decodedEntity) || true === empty($decodedEntity)) {
			$helper -> debugData('[INFO] Call to MagentoStorage::load($id) with the id of ' . $id . ' could not decrypt & unserialize the entity ' . $entity . '.');
			throw new \Exception('[INFO] Call to MagentoStorage::load($id) with the id of ' . $id . ' could not decrypt & unserialize the entity ' . $entity . '.');
		}

		$helper -> debugData('[INFO] Call to MagentoStorage::load($id) with the id of ' . $id . ' successfully decrypted & unserialized the entity ' . $entity . '.');

		return $decodedEntity;
	}

}
