<?php include("header.php"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-calendar"></i> Janurary 2019</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#print"><i class="fas fa-print"></i> Print</button>
    <select class="form-control mt-5">
      <option>Janurary</option>
      <option>February</option>
      <option>March</option>
      <option>April</option>
    </select>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead class="thead-dark">
          <tr>
            <th class="text-center">Sunday</th>
            <th class="text-center">Monday</th>
            <th class="text-center">Tuesday</th>
            <th class="text-center">Wednesday</th>
            <th class="text-center">Thursday</th>
            <th class="text-center">Friday</th>
            <th class="text-center">Saturday</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <p>1</p>
              <span class="badge badge-primary">Security Assessment</span>
              <br>
              <span class="badge badge-danger">INV-001 Overdue</span>
            </td>
            <td>2</td>
            <td>3</td>
            <td>4</td>
            <td>5</td>
            <td>6</td>
            <td>7</td>
          </tr>
          <tr>
            <td>8</td>
            <td>9</td>
            <td>10</td>
            <td>11</td>
            <td>12</td>
            <td>13</td>
            <td>14</td>
          </tr>
          <tr>
            <td>15</td>
            <td>16</td>
            <td>17</td>
            <td>18</td>
            <td>19</td>
            <td>20</td>
            <td>21</td>
          </tr>
          <tr>
            <td>22</td>
            <td>23</td>
            <td>24</td>
            <td>25</td>
            <td>26</td>
            <td>27</td>
            <td>28</td>
          </tr>
          <tr>
            <td>29</td>
            <td>30</td>
            <td>31</td>
            <td colspan="4"></td>
          </tr>
        </tbody>
      </table>
      <strong>Calendars</strong>
      <ul class="list-unstyled">
        <li><span class="badge badge-primary">Jobs</span></li>
        <li><span class="badge badge-danger">Alerts</span></li>
        <li><span class="badge badge-warning">Expiration</span></li>
        <li><span class="badge badge-success">Payments</span></li>
    </div>
  </div>
</div>

<?php include("footer.php");