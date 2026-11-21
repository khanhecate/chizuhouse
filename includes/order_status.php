<?php
// Status pesanan, urutan alur, dan labelnya. Dipakai halaman toko dan admin.
const ORDER_FLOW = ['diterima' => 'Pesanan Diterima', 'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai'];
const ORDER_STATUSES = ORDER_FLOW + ['dibatalkan' => 'Dibatalkan'];
