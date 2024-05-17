# Terraform 不自动更新 CloudRun service

本文撰写时的年份: 2024-05

当使用 Terraform 来管理容器服务的时候, 第一次部署没有任何问题, 但是第二次部署的时候, 就会发现由于 docker image 的名字没有变, 所以 Terraform 不会自动更新容器服务

相关的 issue 有很多: [https://github.com/hashicorp/terraform-provider-google/issues/6706](https://github.com/hashicorp/terraform-provider-google/issues/6706)

以 GoogleCloud CloudRun service 举例:

```
resource "google_cloud_run_v2_service" "default_service" {
  name     = "default-service"
  location = "asia-east2"
  template {
    containers {
      image = "asia-east2-docker.pkg.dev/projectId/repositoryName/default-service"
    }
  }
}
```

在 CI 中构建出新版本的微服务镜像之后, 那肯定是以 latest 标签推送到 docker repository 的, 所以在 Terraform 中使用的镜像名不会改变

于是问题就来了, 正因为镜像名没有变, 导致这个 Terraform `resource` 没有改动, 所以 Terraform 会认为此资源不需要更新, 执行 `terraform plan` 命令的时候这个资源会被跳过

这个问题应该在其他服务商的类似的容器服务里也会遇到. 查了很多 StackOverflow 问答之后, 主流解决方案大多是在镜像构建之后以变量形式传入镜像的 hash 给 Terraform, 又或者是从 docker repository 里读取最新镜像的 hash, 然后把 hash 拼到镜像名后面. 但是这些方式太恶心了

在研究了 `gcloud` 命令行是如何部署 CloudRun 之后, 得到了启发

CloudRun 底层使用的是 knative, 所以可以在 CloudRun 的 revision 页面看到最终使用的 knative 的 yaml, 而在命令行部署出来的 revision 会有一条特别的 label:

```yaml
apiVersion: serving.knative.dev/v1
kind: Revision
metadata:
  xxx: xxx
  labels:
    xxx: xxx
    client.knative.dev/nonce: tnffyvylsw
```

注意看其中的 `client.knative.dev/nonce` , 这是 `gcloud` 命令行自动生成出来的随机字符串, 每个 revision 的一定都不一样

同样的, 我们可以在 Terraform 中也使用随机字符串使每一次执行时的资源描述都不一样

```
resource "random_string" "nonce" {
  length  = 10
  numeric = false
  special = false
  lower   = true
  upper   = false
  keepers = {
    timestamp = timestamp()
  }
}

resource "google_cloud_run_v2_service" "default_service" {
  name     = "default-service"
  location = "asia-east2"
  template {
    labels = {
      "terraform-deployment" : random_string.nonce.result
    }
    containers {
      image = "asia-east2-docker.pkg.dev/projectId/repositoryName/default-service"
    }
  }
}
```

用当前时间戳来做随机字符串的 keeper, 所以每次部署随机字符串都会改变. 而用随机字符串做 "default\_service" 的 label, 所以每一次部署时 "default\_service" 这个资源一定会改变

这样就能让 Terraform 每一次都重新部署 CloudRun service

另外顺便一提, CloudRun job 是没有这个问题的, CloudRun job 会在每次执行时重新拉取一遍镜像, 所以只要 docker image 的某个 tag (比如说 latest)的内容物被更新了, CloudRun job 的执行内容就会自动改变, 不需要重新部署

但是 CloudRun service 会在部署时将 docker tag 转换为其 hash 来"固化"版本, 所以不论 docker 有没有更新, 都不会影响已经部署了的 service, 想要执行新的镜像就必须重新部署. 而 tag 不变的情况下 Terraform 又不会重新部署, 这才引出上述问题
