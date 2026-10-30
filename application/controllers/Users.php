<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Users extends MY_Controller
{
    public function index(): void { $this->requireAdmin(); $this->render('users/index',['users'=>$this->listUsers()]); }
    public function create(): void { $this->form(); }
    public function edit(int $id): void { $this->form($id); }
    public function store(): void { $this->requirePost(); $this->save(); }
    public function update(int $id): void { $this->requirePost(); $this->save($id); }
    public function toggle_status(int $id): void
    {
        $this->requirePost(); $this->requireAdmin();
        $user = $this->db->where('id', $id)->get('users')->row_array();
        if (!$user) { show_404(); }
        $status = $user['status'] === STATUS_ACTIVE ? STATUS_INACTIVE : STATUS_ACTIVE;
        if ($id === (int) $this->currentUser['id'] && $status === STATUS_INACTIVE) {
            $this->json(false, null, lang('cannot_deactivate_self'), ['code' => 'SELF_DEACTIVATION']);
            return;
        }
        $this->db->where('id', $id)->update('users', ['status' => $status]);
        $this->authentication->revokeUser($id);
        $this->json(true, ['id' => $id, 'status' => $status, 'label' => lang($status)], lang('status_updated'));
    }
    public function data(): void
    {
        $this->requireAdmin(); $page=max(1,(int)$this->input->get('page',true)); $perPage=min(100,max(10,(int)$this->input->get('per_page',true)?:10)); $search=trim((string)$this->input->get('q',true));
        $this->db->start_cache()->from('users')->join('warehouses','warehouses.id=users.warehouse_id','left'); if($search!==''){$this->db->group_start()->like('users.name',$search)->or_like('users.email',$search)->or_like('users.role',$search)->or_like('warehouses.name',$search)->group_end();}$this->db->stop_cache();$total=$this->db->count_all_results();$rows=$this->db->select('users.id,users.name,users.email,users.role,users.status,warehouses.name AS warehouse,warehouses.name_ar AS warehouse_ar')->order_by('users.name')->limit($perPage,($page-1)*$perPage)->get()->result_array();$this->db->flush_cache();foreach($rows as &$row){if($this->session->userdata('lang')==='ar'){$row['warehouse']=$row['warehouse_ar'];}$row['role']=lang($row['role']==='admin'?'admin':'warehouse_user');$row['status_value']=$row['status'];$row['status']=lang($row['status']);$row['status_url']=site_url('users/toggle_status/'.$row['id']);$row['edit_url']=site_url('users/edit/'.$row['id']);$row['edit_label']=lang('edit');}$this->json(true,['rows'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$perPage))]]);
    }
    private function listUsers(): array { return $this->db->select('users.*,warehouses.name AS warehouse')->from('users')->join('warehouses','warehouses.id=users.warehouse_id','left')->order_by('users.name')->get()->result_array(); }
    private function form(?int $id=null): void { $this->requireAdmin(); $user=$id?$this->db->where('id',$id)->get('users')->row_array():null; if ($id&&!$user) { show_404(); } $this->render('users/form',['user'=>$user,'warehouses'=>$this->db->where('status',STATUS_ACTIVE)->order_by('name')->get('warehouses')->result_array()]); }
    private function save(?int $id=null): void
    {
        $this->requireAdmin(); $this->form_validation->set_rules('name',lang('name'),'required|max_length[150]'); $this->form_validation->set_rules('email',lang('email'),'required|valid_email|max_length[150]'); if (!$id) { $this->form_validation->set_rules('password',lang('password'),'required|min_length[8]|max_length[100]'); }
        $role=$this->input->post('role',true)===ROLE_WAREHOUSE?ROLE_WAREHOUSE:ROLE_ADMIN; if ($role===ROLE_WAREHOUSE) { $this->form_validation->set_rules('warehouse_id',lang('warehouse'),'required|is_natural_no_zero'); }
        if (!$this->form_validation->run()) { $this->form($id); return; }
        $email=strtolower(trim((string)$this->input->post('email',true))); $duplicate=$this->db->where('email',$email); if ($id) { $duplicate->where('id !=',$id); } if ($duplicate->count_all_results('users')) { $this->session->set_flashdata('flash',['type'=>'error','message'=>lang('email_taken')]); redirect($id?'users/edit/'.$id:'users/create'); }
        $status=$this->input->post('status',true)===STATUS_INACTIVE?STATUS_INACTIVE:STATUS_ACTIVE; if ($id===(int)$this->currentUser['id']&&$status===STATUS_INACTIVE) { $this->session->set_flashdata('flash',['type'=>'error','message'=>lang('cannot_deactivate_self')]); redirect('users/edit/'.$id); }
        $data=['name'=>trim((string)$this->input->post('name',true)),'email'=>$email,'role'=>$role,'warehouse_id'=>$role===ROLE_WAREHOUSE?(int)$this->input->post('warehouse_id',true):null,'preferred_lang'=>$this->input->post('preferred_lang',true)==='en'?'en':'ar','status'=>$status]; $password=(string)$this->input->post('password',false); if ($password!=='') { $data['password_hash']=password_hash($password,PASSWORD_BCRYPT,['cost'=>12]); }
        if ($id) { $this->db->where('id',$id)->update('users',$data); $this->authentication->revokeUser($id); } else { $this->db->insert('users',$data); }
        $this->session->set_flashdata('flash',['type'=>'success','message'=>'User saved.']); redirect('users');
    }
}
