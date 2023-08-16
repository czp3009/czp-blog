# 在 Google Storage 中部署 SPA

本文撰写时的年份: 2023

谢天谢地, 你们谷歌终于在去年底搞出了 Storage 的 404 页面配置.

[https://cloud.google.com/storage/docs/hosting-static-website#specialty-pages](https://cloud.google.com/storage/docs/hosting-static-website#specialty-pages)

单页应用的最大问题是, 虽然全都是静态页面, 但是浏览器刷新时, 实际访问的 path 不对应服务器上的任何文件. 因此若是通常用 Nginx 或者别的 web server 部署 SPA 时都要额外加一条规则, 让 web server 将所有 404 的请求分配到首页(index.html)去.

而谷歌 Storage 现在才支持这种功能:

```bash
gcloud storage buckets update gs://my-static-assets --web-main-page-suffix=index.html --web-error-page=index.html
```

把首页和 404 页面都配置到 index.html. 这下终于 SPA 了起来.

但是事情仍未结束, 你们谷歌 Storage 自定义域名不支持 SSL, 还必须再套一层负载均衡, 妥妥的骗钱: [https://cloud.google.com/storage/docs/hosting-static-website#lb-ssl](https://cloud.google.com/storage/docs/hosting-static-website#lb-ssl)

本来以 Storage 低廉的价格终于可以很便宜的部署一个 SPA 网站, 结果算上负载均衡, 又不便宜了 XD
