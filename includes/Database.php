<?php
/**
 * Clase Database - Maneja la conexión y operaciones con la base de datos
 */
class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $conn;
    
    /**
     * Constructor - Inicializa los parámetros de conexión
     */
    public function __construct() {
        require_once 'config.php';
        
        $this->host = DB_HOST;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->database = DB_NAME;
        
        $this->connect();
    }
    
    /**
     * Establece la conexión con la base de datos
     */
    private function connect() {
    $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
    
    // Verificar conexión
    if ($this->conn->connect_error) {
        die("Error de conexión: " . $this->conn->connect_error);
    }

    // Establecer el conjunto de caracteres
    $this->conn->set_charset("utf8mb4");

    // 🕐 Establecer zona horaria local (Ecuador)
    date_default_timezone_set('America/Guayaquil');
    $this->conn->query("SET time_zone = '-05:00'");
}

    
    /**
     * Obtiene la conexión a la base de datos
     */
    public function getConnection() {
        // Verificar si la conexión está activa
        if (!$this->conn || !$this->conn->ping()) {
            $this->connect();
        }
        return $this->conn;
    }
    
    /**
     * Prepara una sentencia SQL
     */
    public function prepare($sql) {
        return $this->getConnection()->prepare($sql);
    }
    
    /**
     * Ejecuta una consulta SQL
     */
    public function query($sql) {
        return $this->getConnection()->query($sql);
    }
    
    /**
     * Cierra la conexión a la base de datos
     */
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * Inicia una transacción
     */
    public function beginTransaction() {
        $this->getConnection()->begin_transaction();
    }
    
    /**
     * Confirma una transacción
     */
    public function commit() {
        $this->getConnection()->commit();
    }
    
    /**
     * Revierte una transacción
     */
    public function rollback() {
        $this->getConnection()->rollback();
    }
    
    /**
     * Escapa caracteres especiales en una cadena para usar en SQL
     */
    public function escapeString($string) {
        return $this->getConnection()->real_escape_string($string);
    }
    
    /**
     * Obtiene el ID del último registro insertado
     */
    public function getLastInsertId() {
        return $this->getConnection()->insert_id;
    }
}

/**
 * Función auxiliar para ejecutar consultas preparadas
 */
function executeQuery($sql, $params = [], $types = "") {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat("s", count($params));
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Para SELECT, INSERT, UPDATE, DELETE
    if ($result) {
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    } else {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }
}

/**
 * Función para obtener un solo registro
 */
function getSingleRecord($sql, $params = [], $types = "") {
    $result = executeQuery($sql, $params, $types);
    return $result ? $result[0] : null;
}