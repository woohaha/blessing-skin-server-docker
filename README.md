## Blessing Skin Server 皮膚站Docker鏡像

### 使用指南
1. `docker build -t littleskin:latest .` 構建鏡像
2. `docker run -p 8080:8080 --name skin --network=db-network littleskin:latest` 運行

### 開發指南
1. 下載最新版 [Blessing Skin Server](https://github.com/bs-community/blessing-skin-server/releases) 放在src文件夾
2. 構建鏡像