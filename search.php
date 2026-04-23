<?php
header("Access-Control-Allow-Origin: *", true);
header("Access-Control-Allow-Methods: GET, POST, OPTIONS", true);
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With", true);
header("Vary: Origin", true);
header('Content-Type: application/json', true);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'search';
$query = isset($_GET['q']) ? $_GET['q'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'video'; 
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

function fetch_html($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept-Language: en-US,en;q=0.9'
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function extract_yt_data($html) {
    $patterns = [
        '/var ytInitialData = (.*?);<\/script>/',
        '/window\["ytInitialData"\] = (.*?);<\/script>/',
        '/ytInitialData = (.*?);/'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data) return $data;
        }
    }
    return null;
}

if ($endpoint === 'video') {
    if (empty($id)) { echo json_encode(["error" => "No video ID"]); exit; }
    $html = fetch_html("https://www.youtube.com/watch?v=" . $id);
    $ytData = extract_yt_data($html);
    if (!$ytData) { echo json_encode(["error" => "Could not extract data"]); exit; }
    
    $results = $ytData['contents']['twoColumnWatchNextResults']['results']['results']['contents'] ?? [];
    $videoPrimary = null; $videoSecondary = null;
    foreach($results as $item) {
        if (isset($item['videoPrimaryInfoRenderer'])) $videoPrimary = $item['videoPrimaryInfoRenderer'];
        if (isset($item['videoSecondaryInfoRenderer'])) $videoSecondary = $item['videoSecondaryInfoRenderer'];
    }
    
    $description = "";
    if (isset($videoSecondary['attributedDescription']['content'])) {
        $description = $videoSecondary['attributedDescription']['content'];
    } elseif (isset($videoSecondary['description']['runs'])) {
        foreach ($videoSecondary['description']['runs'] as $run) {
            $description .= $run['text'] ?? '';
        }
    }
    
    if (empty($description) && isset($ytData['engagementPanels'])) {
        foreach ($ytData['engagementPanels'] as $panel) {
            $renderer = $panel['engagementPanelSectionListRenderer'] ?? null;
            if ($renderer && ($renderer['panelIdentifier'] ?? '') === 'engagement-panel-structured-description') {
                $content = $renderer['content']['structuredDescriptionContentRenderer']['items'] ?? [];
                foreach ($content as $item) {
                    if (isset($item['expandableVideoDescriptionBodyRenderer']['description']['runs'])) {
                        foreach ($item['expandableVideoDescriptionBodyRenderer']['description']['runs'] as $run) {
                            $description .= $run['text'] ?? '';
                        }
                    }
                }
            }
        }
    }

    $recommended = [];
    $secondary = $ytData['contents']['twoColumnWatchNextResults']['secondaryResults']['secondaryResults']['results'] ?? [];
    foreach($secondary as $item) {
        if (isset($item['compactVideoRenderer'])) {
            $cv = $item['compactVideoRenderer'];
            $recommended[] = [
                'videoId' => $cv['videoId'],
                'title' => $cv['title']['simpleText'] ?? '',
                'author' => $cv['shortBylineText']['runs'][0]['text'] ?? '',
                'videoThumbnails' => $cv['thumbnail']['thumbnails'] ?? []
            ];
        }
    }

    echo json_encode([
        'videoId' => $id,
        'title' => $videoPrimary['title']['runs'][0]['text'] ?? 'Unknown Title',
        'author' => $videoSecondary['owner']['videoOwnerRenderer']['title']['runs'][0]['text'] ?? 'Unknown Author',
        'description' => $description,
        'viewCount' => (int) filter_var($videoPrimary['viewCount']['videoViewCountRenderer']['viewCount']['simpleText'] ?? '0', FILTER_SANITIZE_NUMBER_INT),
        'publishedText' => $videoPrimary['dateText']['simpleText'] ?? '',
        'recommendedVideos' => $recommended
    ]);
    exit;

} elseif ($endpoint === 'channel') {
    if (empty($id)) { echo json_encode(["error" => "No channel ID"]); exit; }
    $html = fetch_html("https://www.youtube.com/channel/$id/videos");
    $ytData = extract_yt_data($html);
    if (!$ytData) { echo json_encode(["error" => "Could not extract data"]); exit; }
    
    $videoItems = [];
    $tabs = $ytData['contents']['twoColumnBrowseResultsRenderer']['tabs'] ?? [];
    foreach($tabs as $tab) {
        if (isset($tab['tabRenderer']['content']['richGridRenderer']['contents'])) {
            $content = $tab['tabRenderer']['content']['richGridRenderer']['contents'];
        } elseif (isset($tab['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['gridRenderer']['contents'])) {
            $content = $tab['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['gridRenderer']['contents'];
        } else { continue; }
        
        foreach($content as $item) {
            $vid = $item['richItemRenderer']['content']['videoRenderer'] ?? $item['gridVideoRenderer'] ?? null;
            if ($vid) {
                $videoItems[] = [
                    'videoId' => $vid['videoId'],
                    'title' => $vid['title']['runs'][0]['text'] ?? $vid['title']['simpleText'] ?? '',
                    'videoThumbnails' => $vid['thumbnail']['thumbnails'] ?? [],
                    'author' => $ytData['header']['c4TabbedHeaderRenderer']['title']['simpleText'] ?? $ytData['metadata']['channelMetadataRenderer']['title'] ?? '',
                    'publishedText' => $vid['publishedTimeText']['simpleText'] ?? ''
                ];
            }
        }
        if (!empty($videoItems)) break;
    }
    echo json_encode($videoItems);
    exit;

} elseif ($endpoint === 'playlist') {
    if (empty($id)) { echo json_encode(["error" => "No playlist ID"]); exit; }
    $html = fetch_html("https://www.youtube.com/playlist?list=$id");
    $ytData = extract_yt_data($html);
    if (!$ytData) { echo json_encode(["error" => "Could not extract data"]); exit; }
    
    $title = $ytData['header']['playlistHeaderRenderer']['title']['simpleText'] ?? $ytData['metadata']['playlistMetadataRenderer']['title'] ?? "Unknown Playlist";
    $contents = $ytData['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['playlistVideoListRenderer']['contents'] ?? [];
    
    $videoItems = [];
    foreach($contents as $item) {
        if (isset($item['playlistVideoRenderer'])) {
            $vid = $item['playlistVideoRenderer'];
            $videoItems[] = [
                'videoId' => $vid['videoId'],
                'title' => $vid['title']['runs'][0]['text'] ?? '',
                'videoThumbnails' => $vid['thumbnail']['thumbnails'] ?? [],
                'author' => $vid['shortBylineText']['runs'][0]['text'] ?? ''
            ];
        }
    }
    echo json_encode(['title' => $title, 'videos' => $videoItems]);
    exit;

} else {
    if (empty($query)) { echo json_encode([]); exit; }
    
    $sp = '';
    if ($type === 'video') {
        if ($sort === 'date') $sp = 'EgQIARAB';
        elseif ($sort === 'views') $sp = 'EgQIARAO';
        else $sp = 'EgIQAQ%3D%3D';
    } elseif ($type === 'channel') {
        $sp = 'EgIQAg%3D%3D';
    } elseif ($type === 'playlist') {
        if ($sort === 'date') $sp = 'EgIIAw%3D%3D';
        elseif ($sort === 'views') $sp = 'EgIIBA%3D%3D';
        else $sp = 'EgIQAw%3D%3D';
    }

    $youtube_url = 'https://www.youtube.com/results?search_query=' . urlencode($query) . '&sp=' . $sp;
    $html = fetch_html($youtube_url);
    $ytData = extract_yt_data($html);
    if (!$ytData) { echo json_encode(["error" => "Could not extract data"]); exit; }
    
    $primary = $ytData['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];
    if (empty($primary) && isset($ytData['contents']['twoColumnSearchResultsRenderer']['primaryContents']['richGridRenderer']['contents'])) {
        $primary = $ytData['contents']['twoColumnSearchResultsRenderer']['primaryContents']['richGridRenderer']['contents'];
    }

    $formatted_results = [];
    foreach ($primary as $section) {
        $items = $section['itemSectionRenderer']['contents'] ?? [$section['richItemRenderer']['content'] ?? null] ?? [];
        foreach ($items as $item) {
            if (!$item) continue;

            if (isset($item['videoRenderer']) && $type === 'video') {
                $vid = $item['videoRenderer'];
                $formatted_results[] = [
                    'type' => 'video',
                    'videoId' => $vid['videoId'],
                    'title' => $vid['title']['runs'][0]['text'] ?? '',
                    'author' => $vid['ownerText']['runs'][0]['text'] ?? '',
                    'videoThumbnails' => $vid['thumbnail']['thumbnails'] ?? [],
                    'viewCount' => (int) filter_var($vid['viewCountText']['simpleText'] ?? '0', FILTER_SANITIZE_NUMBER_INT),
                    'publishedText' => $vid['publishedTimeText']['simpleText'] ?? ''
                ];
            } elseif (isset($item['channelRenderer']) && $type === 'channel') {
                $chan = $item['channelRenderer'];
                $formatted_results[] = [
                    'type' => 'channel',
                    'authorId' => $chan['channelId'],
                    'author' => $chan['title']['simpleText'] ?? '',
                    'authorThumbnails' => $chan['thumbnail']['thumbnails'] ?? [],
                    'subCount' => (int) filter_var($chan['subscriberCountText']['simpleText'] ?? '0', FILTER_SANITIZE_NUMBER_INT),
                    'description' => $chan['descriptionSnippet']['runs'][0]['text'] ?? ''
                ];
            } elseif (isset($item['playlistRenderer']) && $type === 'playlist') {
                $play = $item['playlistRenderer'];
                $thumbUrl = $play['thumbnails'][0]['thumbnails'][0]['url'] ?? $play['thumbnail']['thumbnails'][0]['url'] ?? '';
                $formatted_results[] = [
                    'type' => 'playlist',
                    'playlistId' => $play['playlistId'],
                    'title' => $play['title']['runs'][0]['text'] ?? $play['title']['simpleText'] ?? '',
                    'author' => $play['longBylineText']['runs'][0]['text'] ?? $play['shortBylineText']['runs'][0]['text'] ?? '',
                    'videoCount' => (int) filter_var($play['videoCountText']['runs'][0]['text'] ?? $play['videoCount'] ?? '0', FILTER_SANITIZE_NUMBER_INT),
                    'playlistThumbnail' => $thumbUrl
                ];
            }
            elseif (isset($item['lockupViewModel'])) {
                $vm = $item['lockupViewModel'];
                $cType = $vm['contentType'] ?? '';
                $contentId = $vm['contentId'] ?? '';
                $meta = $vm['metadata']['lockupMetadataViewModel'] ?? [];
                
                if ($cType === 'LOCKUP_CONTENT_TYPE_PLAYLIST' && $type === 'playlist') {
                    $thumbUrl = $vm['contentImage']['collectionThumbnailViewModel']['primaryThumbnail']['thumbnailViewModel']['image']['sources'][0]['url'] ?? '';
                    
                    // Improved count extraction
                    $vCount = 0;
                    
                    // Check overlays (badge text)
                    $overlays = $vm['contentImage']['collectionThumbnailViewModel']['primaryThumbnail']['thumbnailViewModel']['overlays'] ?? [];
                    foreach($overlays as $overlay) {
                        if (isset($overlay['thumbnailOverlayBadgeViewModel']['thumbnailBadges'])) {
                            foreach($overlay['thumbnailOverlayBadgeViewModel']['thumbnailBadges'] as $badge) {
                                if (isset($badge['thumbnailBadgeViewModel']['text'])) {
                                    $txt = $badge['thumbnailBadgeViewModel']['text'];
                                    if (strpos($txt, 'video') !== false) {
                                        $vCount = (int) filter_var($txt, FILTER_SANITIZE_NUMBER_INT);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Recursive metadata check for count and author
                    $rows = $meta['metadata']['contentMetadataViewModel']['metadataRows'] ?? [];
                    $author = "";
                    foreach($rows as $row) {
                        foreach($row['metadataParts'] ?? [] as $part) {
                            $txt = $part['text']['content'] ?? '';
                            if (strpos($txt, 'video') !== false && $vCount === 0) {
                                $vCount = (int) filter_var($txt, FILTER_SANITIZE_NUMBER_INT);
                            } elseif ($txt !== "Playlist" && strpos($txt, 'video') === false && strpos($txt, 'updated') === false && empty($author)) {
                                $author = $txt;
                            }
                        }
                    }

                    $formatted_results[] = [
                        'type' => 'playlist',
                        'playlistId' => $contentId,
                        'title' => $meta['title']['content'] ?? '',
                        'author' => $author,
                        'videoCount' => $vCount,
                        'playlistThumbnail' => $thumbUrl
                    ];
                }
                elseif ($cType === 'LOCKUP_CONTENT_TYPE_VIDEO' && $type === 'video') {
                    $thumbUrl = $vm['contentImage']['thumbnailViewModel']['image']['sources'][0]['url'] ?? '';
                    $author = ""; $views = 0; $pub = "";
                    $rows = $meta['metadata']['contentMetadataViewModel']['metadataRows'] ?? [];
                    if (isset($rows[0]['metadataParts'])) {
                        $parts = $rows[0]['metadataParts'];
                        $author = $parts[0]['text']['content'] ?? '';
                    }
                    if (isset($rows[1]['metadataParts'])) {
                        $parts = $rows[1]['metadataParts'];
                        $views = (int) filter_var($parts[0]['text']['content'] ?? '0', FILTER_SANITIZE_NUMBER_INT);
                        $pub = $parts[1]['text']['content'] ?? '';
                    }
                    $formatted_results[] = [
                        'type' => 'video',
                        'videoId' => $contentId,
                        'title' => $meta['title']['content'] ?? '',
                        'author' => $author,
                        'videoThumbnails' => [['url' => $thumbUrl]],
                        'viewCount' => $views,
                        'publishedText' => $pub
                    ];
                }
            }
        }
    }
    echo json_encode($formatted_results, JSON_PRETTY_PRINT);
}
?>