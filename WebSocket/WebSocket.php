<?php
/**
 * websocket class
 * 2018-01-24
 * @ sane
 * 
 */
class WebSocket {

    static public $instance;

    private $socket;
	private $accept = [];
    private $hands = [];
    
    /**
     * construct function
     *
     * @param [type] $host
     * @param [type] $port
     * @param [type] $max
     */
	function __construct( $host, $port, $max )
	{
		$this -> socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, TRUE);
		socket_bind($this->socket, $host,$port);
		socket_listen($this->socket,$max); 
        // print_r($this->socket);
	}

    /**
     * instance function
     *
     * @param [type] $host
     * @param [type] $port
     * @param [type] $max
     * @return void
     */
	public function getInstance( $host, $port, $max )
	{
        $i = 0;
		while (true) {

			$cycle = $this->accept;
			$cycle[] = $this->socket;
			socket_select($cycle, $write, $except, null);

			foreach ( $cycle as $sock ) {
				if( $sock == $this->socket ) {
					$this->accept[] = socket_accept($sock);
					$arr =  array_keys($this->accept);
					$key = end($arr);
					$this->hands[$key] = false;
				}else{
					$length = socket_recv($sock, $buffer, 204800, null);
                    $key = array_search($sock, $this->accept);
                    
					if( !$this->hands[$key] ) {
						$this->doHandShake($sock,$buffer,$key);
					}else if( $length < 1 ){
                        $this->close($sock);
                    }else{
						//decond data that was sended from client
                        $data = $this->decode($buffer);
                        // print_r("\nThis is the data received from client,".$data);
                        //coding client's data
                        $data = "This is the data sent by server".$i.", so smart";
                        $data = $this->encode($data);
                        // print_r($data);

                        //send message
						foreach ($this->accept as $client) {
							socket_write($client,$data ,strlen($data));
						}
                        $i++;	
					}		 
				}
			}
			sleep(1);
		}
	}

	/**
     * handshake with client firstly function
     *
     * @param [type] $sock
     * @param [type] $data
     * @param [type] $key
     * @return void
     */
    public function doHandShake( $sock, $data, $key ) {
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $data, $match)) {
            $response = base64_encode(sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            $upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
                        "Upgrade: websocket\r\n" .
                        "Connection: Upgrade\r\n" .
                        "Sec-WebSocket-Accept: " . $response . "\r\n\r\n";
            socket_write($sock, $upgrade, strlen($upgrade));
            $this->hands[$key] = true;
        }
    }

    /**
     * close client connect function
     *
     * @param [type] $sock
     * @return void
     */
    public function close($sock) {
        $key = array_search($sock, $this->accept);
        socket_close($sock);
        unset($this->accept[$key]);
        unset($this->hands[$key]);
    }

    /**
     * character decoding function
     *
     * @param [type] $buffer
     * @return void
     */
    public function decode($buffer) {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } 
        else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } 
        else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }

    /**
     * character encoding function
     *
     * @param [type] $buffer
     * @return void
     */
    public function encode( $buffer ) {
        $length = strlen($buffer);
        if($length <= 125) {
            return "\x81".chr($length).$buffer;
        } else if($length <= 65535) {
            return "\x81".chr(126).pack("n", $length).$buffer;
        } else {
            return "\x81".char(127).pack("xxxxN", $length).$buffer;
        }
    }

    /**
     * set self property function
     *
     * @param [type] $selfName
     * @param [type] $ParaName
     * @return void
     */
    public function _set( $selfName, $ParaName ){
        $this -> $selfName = $ParaName;
    }

    /**
     * get self property function
     *
     * @param [type] $selfName
     * @return void
     */
    public function _get( $selfName ){
        return $this -> $selfName;
    }

    private function _clone(){

    }
}