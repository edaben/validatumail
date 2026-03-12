<?php
/**
 * Configuración de Base de Datos para SaaS
 * 
 * Este archivo maneja la conexión a MySQL para el sistema SaaS
 * Completamente independiente del sistema actual
 */

class SaasDatabase
{
    private static $instance = null;
    private $connection;

    // Configuración de la base de datos (usando variables de entorno o valores por defecto)
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset = 'utf8mb4';

    private function __construct()
    {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->dbname = getenv('DB_NAME') ?: 'geocontrol_saasvalidamail';
        $this->username = getenv('DB_USER') ?: 'validamail';
        $this->password = getenv('DB_PASSWORD') ?: 'Rastro@2228';

        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int) $e->getCode());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }
}