<div class="invoice-footer-details @if(count($bankAccounts) === 0) company-only @endif">
  <div class="company-details">
    <strong>{{ $companyInformation['name'] }}</strong>
    <div>〒{{ $companyInformation['postal_code'] }}　{{ $companyInformation['address'] }}</div>
    <div>TEL {{ $companyInformation['phone'] }}@if($companyInformation['fax'] !== '')　FAX {{ $companyInformation['fax'] }}@endif</div>
    <div>登録番号 {{ $companyInformation['registration_number'] }}</div>
  </div>
  @if(count($bankAccounts) > 0)
    <div class="bank-details">
      <strong>お振込先</strong>
      @foreach($bankAccounts as $account)
        <div class="bank-account-text">{{ $account['text'] }}</div>
      @endforeach
    </div>
  @endif
</div>
