import {
  Bell,
  ArrowsClockwise,
  CalendarBlank,
  CaretDown,
  CaretLeft,
  CaretRight,
  ClipboardText,
  Copy,
  DotsThreeVertical,
  MagnifyingGlass,
  Plus,
  PencilSimple,
  Question,
  Trash,
  X,
} from "@phosphor-icons/react";
import { useMemo, useState } from "react";

const navItems = ["受注", "出荷", "商品", "取引先", "在庫", "請求", "レポート", "マスター", "設定"];

const orders = [
  ["PO-2505-00128", "株式会社 酒商 山田", "2025/05/16", "¥842,400", "received"],
  ["PO-2505-00127", "東京酒販 株式会社", "2025/05/16", "¥1,235,520", "partially_instructed"],
  ["PO-2505-00126", "名古屋国分 株式会社", "2025/05/15", "¥672,320", "received"],
  ["PO-2505-00125", "株式会社 大阪酒販", "2025/05/15", "¥1,102,860", "instructed"],
  ["PO-2505-00124", "九州酒類販売 株式会社", "2025/05/14", "¥533,280", "received"],
  ["PO-2505-00123", "仙台酒類 株式会社", "2025/05/14", "¥318,120", "cancelled"],
  ["PO-2505-00122", "広島酒販 株式会社", "2025/05/13", "¥726,000", "received"],
];

const statusLabels = {
  received: "受注済み",
  partially_instructed: "一部出荷指示済み",
  instructed: "出荷指示済み",
  cancelled: "取消",
};

const products = [
  ["101001", "白鶴 大吟醸", "白鶴 大吟醸シリーズ", "720ml", "大吟醸", "6", "6", "本", "¥4,200", "¥25,200"],
  ["101002", "白鶴 純米吟醸 山田錦", "白鶴 山田錦シリーズ", "720ml", "純米吟醸", "6", "12", "本", "¥2,300", "¥27,600"],
  ["101003", "白鶴 特別純米酒", "特別純米シリーズ", "1,800ml", "特別純米酒", "6", "6", "本", "¥2,100", "¥12,600"],
  ["101004", "白鶴 まる", "まるシリーズ", "2,000ml", "普通酒", "6", "6", "本", "¥1,300", "¥7,800"],
  ["101005", "白鶴 生貯蔵酒 冷酒", "生貯蔵酒シリーズ", "300ml", "生貯蔵酒", "12", "24", "本", "¥520", "¥12,480"],
  ["101006", "白鶴 淡麗純米", "淡麗純米シリーズ", "720ml", "純米酒", "6", "18", "本", "¥1,650", "¥29,700"],
  ["101007", "白鶴 上撰", "上撰シリーズ", "1,800ml", "本醸造", "6", "12", "本", "¥1,780", "¥21,360"],
  ["101008", "白鶴 蔵出し原酒", "限定流通シリーズ", "720ml", "原酒", "6", "6", "本", "¥3,400", "¥20,400"],
  ["101009", "白鶴 にごり酒", "季節商品シリーズ", "720ml", "リキュール", "6", "12", "本", "¥1,250", "¥15,000"],
  ["101010", "白鶴 梅酒原酒", "梅酒シリーズ", "500ml", "梅酒", "12", "24", "本", "¥980", "¥23,520"],
  ["101011", "白鶴 スパークリング", "発泡清酒シリーズ", "300ml", "発泡清酒", "12", "36", "本", "¥640", "¥23,040"],
  ["101012", "白鶴 特撰 山廃仕込", "特撰シリーズ", "720ml", "山廃純米", "6", "6", "本", "¥2,800", "¥16,800"],
];

const defaultPrices = Object.fromEntries(products.map((row) => [row[0], { price: row[8], total: row[9] }]));

export function App() {
  const [query, setQuery] = useState("");
  const [activeOrder, setActiveOrder] = useState(orders[0][0]);
  const [rows, setRows] = useState(products);
  const [priceEditor, setPriceEditor] = useState(null);
  const [manualPrices, setManualPrices] = useState({});

  const visibleOrders = useMemo(() => {
    const term = query.trim();
    if (!term) return orders;
    return orders.filter((order) => order.join(" ").includes(term));
  }, [query]);

  const addProduct = () => {
    setRows((current) => [
      ...current,
      ["101006", "白鶴 新規商品", "追加商品シリーズ", "720ml", "普通酒", "6", "1", "本", "¥0", "¥0"],
    ]);
  };

  const updateQuantity = (index, value) => {
    setRows((current) =>
      current.map((row, rowIndex) =>
        rowIndex === index ? [row[0], row[1], row[2], row[3], row[4], row[5], value, row[7], row[8], row[9]] : row,
      ),
    );
  };

  const removeProduct = (index) => {
    setRows((current) => current.filter((_, rowIndex) => rowIndex !== index));
  };

  const openPriceEditor = (index) => {
    setPriceEditor({ index, value: rows[index][8].replace(/[^0-9.]/g, "") });
  };

  const saveManualPrice = () => {
    if (!priceEditor) return;
    const unitPrice = Number(priceEditor.value);
    if (!Number.isFinite(unitPrice) || unitPrice <= 0) return;

    setRows((current) =>
      current.map((row, index) => {
        if (index !== priceEditor.index) return row;
        const total = Number(row[6] || 0) * unitPrice;
        return [...row.slice(0, 8), `¥${unitPrice.toLocaleString("ja-JP")}`, `¥${total.toLocaleString("ja-JP")}`];
      }),
    );
    setManualPrices((current) => ({ ...current, [rows[priceEditor.index][0]]: true }));
    setPriceEditor(null);
  };

  const resetPrice = (index) => {
    const code = rows[index][0];
    const defaultPrice = defaultPrices[code];
    setRows((current) => current.map((row) => row[0] === code ? [...row.slice(0, 8), defaultPrice.price, defaultPrice.total] : row));
    setManualPrices((current) => ({ ...current, [code]: false }));
    setPriceEditor(null);
  };

  return (
    <div className="app">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-logo">白</div>
          <div>
            <strong>白鶴酒造株式会社</strong>
            <span>B2B受注管理システム</span>
          </div>
        </div>

        <nav className="side-nav" aria-label="メニュー">
          {navItems.map((item, index) => (
            <button className={item === "受注" ? "side-item active" : "side-item"} key={item}>
              <span className="side-icon">{["▣", "▤", "▦", "♧", "▥", "◉", "◴", "▦", "⚙"][index]}</span>
              {item}
            </button>
          ))}
        </nav>

        <button className="collapse-menu">
          <CaretLeft size={18} />
          メニューを閉じる
        </button>
      </aside>

      <div className="main">
        <header className="topbar">
          <div className="top-actions">
            <button className="top-button">
              <Bell size={17} />
              通知
              <span className="notification">5</span>
            </button>
            <button className="top-button">
              <Question size={17} />
              ヘルプ
            </button>
            <div className="operator">
              <strong>山田 太郎</strong>
              <span>受注担当</span>
            </div>
            <CaretDown size={16} />
          </div>
        </header>

        <main className="workspace">
          <section className="orders-pane">
            <div className="pane-title">
              <h1>受注一覧</h1>
              <label className="global-search">
                <MagnifyingGlass size={18} />
                <input
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="取引先名・注文番号・商品名で検索"
                />
              </label>
            </div>

            <div className="view-row">
              <span>保存済みビュー</span>
              <button className="select-button">
                受注済み
                <CaretDown size={16} />
              </button>
              <button className="plain-icon">
                <DotsThreeVertical size={20} />
              </button>
            </div>

            <section className="filter-card">
              <div className="filter-heading">
                <strong>絞り込み</strong>
                <button>フィルターをリセット</button>
              </div>
              <FieldSelect label="受注日" value="すべて" />
              <FieldSelect label="取引先" value="すべて" />
              <FieldSelect label="ステータス" value="受注済み" />
            </section>

            <div className="list-meta">
              <span>25件</span>
              <button>⟳ 最新の情報に更新</button>
            </div>

            <div className="order-list">
              {visibleOrders.map((order) => (
                <button
                  className={order[0] === activeOrder ? "order-card active" : "order-card"}
                  key={order[0]}
                  onClick={() => setActiveOrder(order[0])}
                >
                  <div>
                    <strong>
                      {order[0]}
                      {order[0] === activeOrder && <span className="active-dot" />}
                    </strong>
                    <span>{order[1]}</span>
                  </div>
                  <div>
                    <time>{order[2]}</time>
                    <strong>{order[3]}</strong>
                    <em>{statusLabels[order[4]]}</em>
                  </div>
                </button>
              ))}
            </div>

            <footer className="list-pagination">
              <button>
                <CaretLeft size={17} />
              </button>
              <span>1</span>
              <small>/ 3</small>
              <button>
                <CaretRight size={17} />
              </button>
            </footer>
          </section>

          <section className="entry-pane">
            <div className="entry-header">
              <div>
                <h2>新規受注</h2>
                <span className="dirty-dot" />
                <small>未保存の変更があります</small>
              </div>
              <div className="entry-tools">
                <button>
                  <Copy size={18} />
                  複製
                </button>
                <button>
                  <ClipboardText size={18} />
                  テンプレート
                </button>
                <button className="plain-icon">
                  <DotsThreeVertical size={20} />
                </button>
              </div>
            </div>

            <section className="form-card">
              <div className="form-grid top">
                <Field label="取引先" required value="取引先を検索または選択" search select link="取引先を新規登録" linkInline />
                <Field label="受注日" required value="2025/05/16" icon={<CalendarBlank size={18} />} />
                <Field label="希望納品日" value="2025/05/23" icon={<CalendarBlank size={18} />} />
                <Field label="取引先注文番号" value="取引先の注文番号を入力" />
              </div>

              <label className="memo-field">
                <span>備考</span>
                <textarea placeholder="備考を入力" />
              </label>
            </section>

            <section className="detail-card">
              <div className="detail-header">
                <h3>商品明細</h3>
                <button className="blue-button" onClick={addProduct}>
                  <Plus size={18} />
                  商品を追加
                  <CaretDown size={15} />
                </button>
                <button className="sub-button">⌘ よく使う商品</button>
                <button className="sub-button">✎ 一括編集</button>
                <button className="text-danger">明細を削除</button>
                <span>明細数：{rows.length}</span>
              </div>

              <div className="product-table-wrap">
                <div className="product-table">
                  <div className="product-head">
                    <span>行</span>
                    <span>商品コード</span>
                    <span>商品名 / シリーズ名</span>
                    <span>容量</span>
                    <span>特定名称 / 種別</span>
                    <span>入数</span>
                    <span>数量</span>
                    <span>単位</span>
                    <span>単価</span>
                    <span>金額</span>
                    <span>削除</span>
                  </div>
                  {rows.map((row, index) => (
                    <div className="product-row" key={`${row[0]}-${index}`}>
                      <span>{index + 1}</span>
                      <span>{row[0]}</span>
                      <span className="product-copy">
                        <strong>{row[1]}</strong>
                        <small>{row[2]}</small>
                      </span>
                      <span>{row[3]}</span>
                      <span>{row[4]}</span>
                      <span>{row[5]}</span>
                      <input value={row[6]} onChange={(event) => updateQuantity(index, event.target.value)} />
                      <button className="unit-button">
                        {row[7]}
                        <CaretDown size={14} />
                      </button>
                      <button className="price-cell" onClick={() => openPriceEditor(index)}>
                        <strong>{row[8]}</strong>
                        <small className={manualPrices[row[0]] ? "price-source manual" : "price-source"}>
                          {manualPrices[row[0]] ? "今回のみ変更" : index % 3 === 0 ? "取引先価格" : "標準価格"}
                        </small>
                      </button>
                      <strong>{row[9]}</strong>
                      <button className="trash-button" aria-label={`${row[1]}を削除`} onClick={() => removeProduct(index)}>
                        <Trash size={18} />
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            </section>

            {priceEditor && (
              <aside className="price-panel" aria-label="単価を編集">
                <div className="price-panel-header">
                  <div>
                    <small>商品明細</small>
                    <h3>単価を編集</h3>
                  </div>
                  <button aria-label="閉じる" onClick={() => setPriceEditor(null)}><X size={18} /></button>
                </div>
                <div className="price-panel-product">
                  <strong>{rows[priceEditor.index][1]}</strong>
                  <span>{rows[priceEditor.index][0]} ・ {rows[priceEditor.index][3]}</span>
                </div>
                <label className="price-input-field">
                  <span>今回の単価（税込）</span>
                  <div><span>¥</span><input value={priceEditor.value} inputMode="decimal" onChange={(event) => setPriceEditor({ ...priceEditor, value: event.target.value })} /></div>
                </label>
                <div className="price-origin">
                  <span>現在の価格根拠</span>
                  <strong>{manualPrices[rows[priceEditor.index][0]] ? "今回のみ変更" : priceEditor.index % 3 === 0 ? "取引先価格" : "標準価格"}</strong>
                  <small>取引先価格を変更する場合は、価格マスタを更新します。</small>
                </div>
                <div className="price-panel-actions">
                  <button className="reset-price" onClick={() => resetPrice(priceEditor.index)}><ArrowsClockwise size={16} />適用価格に戻す</button>
                  <button className="save-price" onClick={saveManualPrice}><PencilSimple size={16} />今回の単価を保存</button>
                </div>
              </aside>
            )}
          </section>
        </main>

        <footer className="bottom-summary">
          <div className="summary-values">
            <span>
              <small>小計（税抜）</small>
              <strong>¥85,680</strong>
            </span>
            <span>
              <small>消費税（10%）</small>
              <strong>¥8,568</strong>
            </span>
            <span className="total">
              <small>合計（税込）</small>
              <strong>¥94,248</strong>
            </span>
            <button>
              <CaretRight size={17} />
              詳細を表示
            </button>
          </div>

          <div className="footer-actions">
            <button className="register-button">
              受注を登録
              <span />
              <CaretDown size={18} />
            </button>
          </div>
        </footer>
      </div>
    </div>
  );
}

function FieldSelect({ label, value }) {
  return (
    <label className="filter-field">
      <span>{label}</span>
      <button>
        {value}
        <CaretDown size={16} />
      </button>
    </label>
  );
}

function Field({ label, value, required, search, select, icon, link, linkInline, muted }) {
  return (
    <label className="field">
      <div className="field-label">
        <span>
          {label}
          {required && <b> *</b>}
        </span>
        {linkInline && <button className="field-link">{link}</button>}
      </div>
      <div className={muted ? "field-box muted" : "field-box"}>
        {search && <MagnifyingGlass size={17} />}
        <span>{value}</span>
        {icon}
        {select && <CaretDown size={16} />}
      </div>
      {link && !linkInline && <button className="field-link">{link}</button>}
    </label>
  );
}
