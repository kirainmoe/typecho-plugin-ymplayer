<?php
class ymplayer_Action extends Typecho_Widget implements Widget_Interface_Do
{

    public function execute()
    {
    }

    public function action()
    {
    }

    public function ajax()
    {
        $type = $this->request->get('type');
        if (method_exists($this, $type))
        {
            $this->$type();
        }
        else
        {
            $this->throw404();
        }
    }

    protected function playlist()
    {
        $playlist = $this->request->get('playlist');

        if (is_null($playlist))
            $this->throw404();
        $url   = 'http://music.163.com/api/playlist/detail/?id=' . $playlist;
        $json  = $this->fetch($url);
        $data  = json_decode($json, true);
        $res = $data['result']['tracks'];

        $dom = "";

        for ($c = 0; $c < count($res); $c++)
        {
            $src = $res[$c]['mp3Url'];
            $name = $res[$c]['name'];
            $artist = $res[$c]['artists'][0]['name'];
            $cover = $res[$c]['album']['picUrl'];
            $id = $res[$c]['id'];
            $url = 'http://music.163.com/api/song/media?id=' . $id;
            $lrc = json_decode($this->fetch($url),true);
            ($lrc['code'] == 200 && isset($lrc['lyric'])) ? $lrc = $lrc['lyric'] : $lrc = "";
            $dom .= "<song src=\"{$src}\" song=\"{$name}\" artist=\"{$artist}\" cover=\"{$cover}\" mid=\"{$id}\">{$lrc}</song>\n";
        }
        echo $dom;
    }

    protected function song()
    {
        $id = $this->request->get('id');
        if (is_null($id))
        {
            $this->throw404();
        }
        $cache = $this->get_cache($id, 'song');
        if (!$cache)
        {
            $url   = 'http://music.163.com/api/song/detail/?id=' . $id . '&ids=%5B' . $id . '%5D';
            $json  = $this->fetch($url);
            $data  = json_decode($json, true);
            $array = array();

            if ($data['code'] == 200)
            {
                $array = array(
                    'title'   => $data['songs'][0]['name'],
                    'song_id' => $data['songs'][0]['id'],
                    'src'     => $data['songs'][0]['mp3Url'],
                    'cover'   => $data['songs'][0]['album']['picUrl'],
                    'artist'  => $data['songs'][0]['artists'][0]['name'],
                );
                $this->set_cache($id, 'song', json_encode($array));
                $this->response->throwJson($array);
            }
            else
            {
                $this->throw404();
            }
        }
        else
        {
            exit($cache);
        }

    }

    protected function lyric()
    {
        $id = $this->request->get('id');
        if (is_null($id))
        {
            $this->throw404();
        }
        $cache = $this->get_cache($id, 'lyric');

        if (!$cache)
        {
            $url = 'http://music.163.com/api/song/media?id=' . $id;

            $json = $this->fetch($url);

            $result = json_decode($json, true);
            if ($result['code'] == 200)
            {
                if ($result['lyric'])
                {
                    $array = array(
                        'status' => true,
                        'lyric'  => $result['lyric'],
                    );
                    $this->set_cache($id, 'lyric', $array['lyric']);
                    $this->response->throwJson($array);
                }
                else
                {
                    $this->response->throwJson(array(
                        'status' => true,
                        'lyric'  => 'not found',
                    ));
                }
            }
        }
        else
        {
            $this->response->throwJson(array(
                'status' => true,
                'lyric'  => $cache,
            ));
        }

        $this->throw404();

    }

    protected function fetch($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36');
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    protected function throw404()
    {
        Typecho_Response::setStatus(404);
        $this->response->throwJson(array('status' => '404 Not Found.'));
    }

    protected function throw403()
    {
        Typecho_Response::setStatus(403);
        $this->response->throwJson(array('status' => '403 Forbidden.'));
    }

    protected function get_cache($id, $type)
    {
        $filename = $this->get_cache_name($id, $type);
        if (is_file($filename))
        {
            return file_get_contents($filename);
        }
        else
        {
            return false;
        }

    }

    protected function set_cache($id, $type, $content)
    {
        $filename = $this->get_cache_name($id, $type);
        file_put_contents($filename, $content);
    }

    protected function get_cache_name($id, $type)
    {
        $cache_dir = dirname(__FILE__) . '/cache';
        if ($type == 'song')
        {
            return $cache_dir . '/' . $id . '.json';
        }
        elseif ($type == 'lyric')
        {
            return $cache_dir . '/' . $id . '.lrc';
        }
        else
        {
            return false;
        }
    }

    protected function checkUpdate()
    {
        $remote          = 'https://kotori.sinaapp.com/ymplayer/latest?path=Plugin.php';
        $local           = dirname(__FILE__) . '/Plugin.php';
        $info            = Typecho_Plugin::parseInfo($remote);
        $latest_version  = $info['version'];
        $info            = Typecho_Plugin::parseInfo($local);
        $current_version = $info['version'];
        $text            = '你的版本是' . $current_version . '，GitHub上游版本是' . $latest_version . '，';
        if ($current_version >= $latest_version)
        {
            $status = false;
            $text .= '无需更新。';
        }
        else
        {
            $status = true;
            $text .= '再次点击按钮进行更新。';
        }
        $this->response->throwJson(array(
            'status' => $status,
            'text'   => $text,
        ));
    }

    protected function downloadUpdate()
    {
        $array = array(
            $this->download_file('Plugin.php'),
            $this->download_file('Action.php'),
            $this->download_file('force.css'),
            $this->download_file('init.js'),
            $this->download_file('dist/ymplayer.css'),
            $this->download_file('dist/ymplayer.min.js'),
        );
        foreach ($array as $value)
        {
            if ($value == false)
            {
                exit('failure');
            }
        }
        exit('success');
    }

    protected function download_file($path)
    {
        $url  = 'https://kotori.sinaapp.com/ymplayer/latest?path=' . $path;
        $path = dirname(__FILE__) . '/' . $path;
        try {
            $ch = curl_init();
            $fp = fopen($path, 'wb');
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            return true;
        }
        catch (Exception $e)
        {
            return false;
        }
    }

}
