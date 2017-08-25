<?php
/**
 * Created by PhpStorm.
 * User: Xc
 * Date: 2017/8/25
 * Time: 10:42
 */

class Client
{
    private $client;

    public function __construct() {
        //异步客户端
        $this->client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->client->set(array(
           /* 'open_eof_check' => true,
            'package_eof' => "\r\n\r\n",*/
            'open_length_check'     => 1,
            'package_length_type'   => 'N',
            'package_length_offset' => 1,       //第N个字节是包长度的值
            'package_body_offset'   => 0,       //第几个字节开始计算长度
            'package_max_length'    => 2000000,  //协议最大长度
            'socket_buffer_size'     => 1024*1024*10, //10M缓存区
        ));
  //      $this->client = new swoole_client(SWOOLE_SOCK_TCP);
        $this->client->on('Connect', array($this, 'onConnect'));
        $this->client->on('Receive', array($this, 'onReceive'));
        $this->client->on('Close', array($this, 'onClose'));
        $this->client->on('Error', array($this, 'onError'));
        $this->client->on('BufferFull', array($this, 'onBufferFull'));
        $this->client->on('BufferEmpty', array($this, 'onBufferEmpty'));
    }
    public function connect() {
        $fp = $this->client->connect("127.0.0.1", 9601 , 1);
        if( !$fp ) {
            echo "Error: {$fp->errMsg}[{$fp->errCode}]\n";
            return;
        }

    }

    public function onConnect( $cli) {
        $message=json_encode([
            'code'=>'demo',
            'status'=>'1'
        ]);
        $length=42+strlen($message);
        $uuid=md5(uniqid(microtime(true),true));
        $this->client->send(pack("C",1));  //消息分隔符  固定传1
        $this->client->send(pack("N",$length));  //整个消息的长度(包头+包体)
        $this->client->send(pack("C",1));      //消息类型
        $this->client->send($uuid);          //请求者ID
        $this->client->send(pack("C",1));    //版本号 固定传1
        $this->client->send(pack("C",0));    //包体加密标识    0-不加密  1-加密；如果需要加密，请先请求密钥
        $this->client->send(pack("C",0));    //服务端响应包体是否需要加密    0-不加密  1-加密；如果需要加密，请先请求密钥
        $this->client->send(pack("C",0));    //0-未压缩 1-压缩；
        $this->client->send($message);

        //根据服务端设置60S请求一次心跳数据
        swoole_timer_tick(60000, function() use($cli,$uuid){
            //发送心跳数据
            $cli->send(pack("C",1));  //消息分隔符  固定传1
            $this->client->send(pack("N",42));  //整个消息的长度
            $this->client->send(pack("C",3));      //消息类型 暂定3为心跳请求
            $this->client->send($uuid);          //请求者ID
            $this->client->send(pack("C",1));    //版本号 固定传1
            $this->client->send(pack("C",0));    //包体加密标识    0-不加密  1-加密；如果需要加密，请先请求密钥
            $this->client->send(pack("C",0));    //服务端响应包体是否需要加密    0-不加密  1-加密；如果需要加密，请先请求密钥
            $this->client->send(pack("C",0));    //0-未压缩 1-压缩；
        });

    }

    public function onReceive( $cli, $data ) {
        $msg_split=unpack("C",$data)[1];
        echo "消息分隔符:".PHP_EOL;
        echo $msg_split.PHP_EOL;

        $data=substr($data,1);
        //获取整个消息的长度
        $msg_length=unpack("N",$data)[1];
        echo "整个消息的长度:".PHP_EOL;
        echo $msg_length.PHP_EOL;

        $data=substr($data,4);
        //消息类型
        $msg_type=unpack("C",$data)[1];
        // TODO 根据消息类型不同进行处理
        echo "消息类型:".PHP_EOL;
        echo $msg_type.PHP_EOL;

        $data=substr($data,1);
        //请求者ID
        $uuid=substr($data,0,32);
        // TODO 记录uuid作为请求者的唯一ID
        echo "请求者ID:".PHP_EOL;
        echo $uuid.PHP_EOL;

        $data=substr($data,32);
        //获取版本号
        $version=unpack("C",$data)[1];
        echo "获取版本号:".PHP_EOL;
        echo $version.PHP_EOL;

        $data=substr($data,1);
        //获取包体加密标识 0-不加密  1-加密
        $cipher=unpack("C",$data)[1];
        // TODO 根据请求进行解密处理
        echo "包体加密标识:".PHP_EOL;
        echo $cipher.PHP_EOL;

        $data=substr($data,1);
        //服务端响应包体是否需要加密标识  0-不需要加密  1-需要加密
        $replyCipher=unpack("C",$data)[1];
        // TODO 根据请求进行加密处理
        echo "响应包体是否加密标识:".PHP_EOL;
        echo $replyCipher.PHP_EOL;

        $data=substr($data,1);
        //获取包体是否需要压缩标识  0-未压缩 1-压缩
        $compress=unpack("C",$data)[1];
        echo "包体是否压缩标识:".PHP_EOL;
        echo $compress.PHP_EOL;

        //消息类型为4为应答心跳消息
        if($msg_type!==4){
            //获取包体
            $data=substr($data,1);
            echo "包体:".PHP_EOL;
            echo $data.PHP_EOL;
        }

    }

    public function onClose( $cli) {
        echo "Client close connection\n";

    }
    public function onError() {
    }

    public function onBufferFull($cli){

    }

    public function onBufferEmpty($cli){

    }

    public function send($data) {
        $this->client->send( $data );
    }

    public function isConnected() {
        return $this->client->isConnected();
    }

}
$cli = new Client();
$cli->connect();