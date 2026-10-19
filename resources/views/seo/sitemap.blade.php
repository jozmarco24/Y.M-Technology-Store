{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        @if (isset($url['lastmod']))
            <lastmod>{{ $url['lastmod'] }}</lastmod>
        @endif
        @if (isset($url['changefreq']))
            <changefreq>{{ $url['changefreq'] }}</changefreq>
        @endif
        @if (isset($url['priority']))
            <priority>{{ $url['priority'] }}</priority>
        @endif
    </url>
@endforeach
</urlset>
