target "bbo-faucet" {
  context = "."
  cache-to = [{type = "inline"}]
  cache-from = [{
    type = "registry"
    ref = "1maa/bbo-faucet:latest"
  }]
  platforms = ["linux/amd64", "linux/arm64"]
  tags = ["1maa/bbo-faucet:latest"]
}
