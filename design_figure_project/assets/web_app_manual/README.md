# Webアプリ説明書 図版一覧

本フォルダは `17_Webアプリ説明書.md` と `17_Webアプリ説明書_Word用.html` から参照する図版の置き場です。

## 収録図版

| 図番号 | ファイル名 | 用途 |
| --- | --- | --- |
| 図1 | `01_system_overview.png` | 公開側と業務側を含むシステム全体像 |
| 図2 | `02_work_sidebar_flow.png` | `/work/*` サイドバーから見た業務メニューの流れ |
| 図3 | `03_quote_lifecycle.png` | 仕様書発行・見積変更申請・承認/却下と現行版の関係 |
| 図4 | `04_master_pricing_flow.png` | Part・価格表・作業費・DSL が見積計算へつながる流れ |
| 図5 | `05_change_request_flow.png` | 変更申請の提出から反映・監査までの流れ |
| 図6 | `06_code_mapping_layers.png` | route / controller / service / view / table の対応イメージ |

## 運用メモ

- 本文と Word 版は PNG を参照します。編集元として SVG も同じフォルダに残しています。
- Microsoft Word で `17_Webアプリ説明書_Word用.html` を開く場合も、この `assets/web_app_manual/` フォルダを同じ相対配置で維持してください。
- 実画面のスクリーンショットに差し替える場合は、同じ PNG ファイル名を維持したまま差し替えると、本文側のリンク修正が不要です。
- PDF 化するときは、章見出しの直後に図版を置き、本文では「図1」「図2」のように参照してください。
