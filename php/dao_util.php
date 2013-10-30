<?php
/**
 * 通用 PHP Dao 操作类
 */
class DaoUtil
{
	const MYSQL_GONE_AWAY = 'mysql server has gone away';

	private $arrDbConfig;

	private $objPdo;
	
	public function __construct(array $arrDbConfig)
	{
		$this->arrDbConfig = $arrDbConfig;
	}

	private function init()
	{
		if ($this->objPdo) {
			try {
				$msg = $this->objPdo->getAttribute(PDO::ATTR_SERVER_INFO);
				$isAlive = stripos($msg, self::MYSQL_GONE_AWAY) === false;
				if ($isAlive) {
					return;
				}
			} catch(Exception $e) {
				$msg = $e->getMessage();
				// 不是 gone away 异常
				if (stripos($msg, self::MYSQL_GONE_AWAY) === false) {
					throw $e;
				}
			}
		}
		$dsn = "mysql:host={$this->arrDbConfig['host']};port={$this->arrDbConfig['port']};dbname={$this->arrDbConfig['database']}";
		$encoding = 'UTF8';
		if (isset($this->arrDbConfig['encoding'])) {
			$encoding = $this->arrDbConfig['encoding'];
		}
		$this->objPdo = new PDO(
			$dsn,
			$this->arrDbConfig['username'],
			$this->arrDbConfig['password'],
			array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES ${encoding}")
		);
	}

	public function fetchAll($sql, $arrValues = array())
	{
		$this->init();
		$pdoStmt = $this->objPdo->prepare($sql);
		$pdoStmt->execute($arrValues);
		return $pdoStmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function fetch($sql, $arrValues = array())
	{
		$this->init();
		$pdoStmt = $this->objPdo->prepare($sql);
		$pdoStmt->execute($arrValues);
		return $pdoStmt->fetch(PDO::FETCH_ASSOC);
	}

    public function execute($sql, $arrValues = array())
	{
		$this->init();
		$pdoStmt = $this->objPdo->prepare($sql);
		$pdoStmt->execute($arrValues);
        return $pdoStmt->rowCount();
	}
}
